<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventSlot;

/**
 * Small dependency-free, downloadable A4 PDF of the public ORBAT.
 * The document contains public nicknames and tactical organization only.
 */
class EventOrbatPdfService
{
    private const ORDER = ['red', 'blue', 'yellow', 'green', 'white'];

    private const PALETTE = [
        'white' => [0.40, 0.45, 0.52],
        'green' => [0.10, 0.53, 0.31],
        'yellow' => [0.67, 0.44, 0.02],
        'red' => [0.75, 0.18, 0.18],
        'blue' => [0.12, 0.37, 0.73],
    ];

    private const LABELS = [
        'white' => 'Blanco',
        'green' => 'Verde',
        'yellow' => 'Amarillo',
        'red' => 'Rojo',
        'blue' => 'Azul',
    ];

    private array $pages = [];
    private string $page = '';
    private float $y = 0;

    public function generate(Event $event, EventOrbatMarkingService $service): string
    {
        $this->pages = [];
        $this->newPage();
        $this->line('SQUAD ALPHA  /  ORGANIZACION DEL ORBAT', 16, true, [0.11, 0.18, 0.27]);
        $this->line((string) $event->name, 12, true);
        $this->line($event->date?->format('d/m/Y H:i') ?? '', 9, false, [0.45, 0.49, 0.55]);
        $this->y -= 13;

        $markings = $service->markings($event);
        $settings = $service->groupSettings($event);
        $occupants = EventSlot::query()->where('event_id', $event->id)
            ->with(['user', 'ally'])->get()->keyBy('slot_key');
        $any = false;

        foreach (($event->orbat['groups'] ?? []) as $group) {
            if (! ($group['visible'] ?? true)) continue;
            $name = trim((string) ($group['name'] ?? ''));
            $slots = collect($group['slots'] ?? [])->filter(
                fn ($slot) => ($slot['visible'] ?? true) && ! empty($slot['slot_key'])
            );
            if ($slots->isEmpty()) continue;
            $any = true;
            $this->requireHeight(92);
            $this->heading(mb_strtoupper($name));
            $setting = $settings[$name] ?? [];
            if (filled($setting['custom_title'] ?? null)) {
                $this->line('Mision: '.$setting['custom_title'], 10, true, [0.28, 0.34, 0.42]);
            }
            $entries = $slots->map(function (array $slot) use ($occupants, $markings): array {
                $key = (string) $slot['slot_key'];
                $occupant = $occupants->get($key);
                $mark = $markings[$key] ?? [];
                return [
                    'slot_key' => $key,
                    'number' => $mark['number'] ?? null,
                    'color' => $mark['color'] ?? 'white',
                    'name' => $occupant?->user?->nick
                        ?? $occupant?->ally_nickname
                        ?? $occupant?->ally?->name
                        ?? '-',
                    'role' => (string) ($slot['name'] ?? ''),
                    'occupied' => $occupant !== null,
                ];
            })->sort(function (array $a, array $b): int {
                $colorOrder = array_flip(self::ORDER);
                return ($colorOrder[$a['color']] ?? 99) <=> ($colorOrder[$b['color']] ?? 99)
                    ?: (($a['number'] ?? PHP_INT_MAX) <=> ($b['number'] ?? PHP_INT_MAX));
            })->values();

            $titles = $setting['color_team_titles'] ?? [];
            foreach (self::ORDER as $color) {
                $label = self::LABELS[$color];
                $count = $entries->filter(fn ($entry) =>
                    $entry['occupied'] && $entry['color'] === $color)->count();
                if ($count < 1) continue;
                $teamTitle = trim((string) ($titles[$color] ?? ''));
                $this->requireHeight(19);
                $this->line($label.' ('.$count.')'.($teamTitle !== '' ? ' - '.$teamTitle : ''), 9, true, self::PALETTE[$color]);
            }

            $this->y -= 5;
            foreach ($entries as $entry) {
                // Unnumbered, empty positions are omitted from the printable roster.
                if (! $entry['occupied'] && $entry['number'] === null) continue;
                $this->requireHeight(21);
                $color = self::PALETTE[$entry['color']] ?? self::PALETTE['white'];
                $this->rectangle(36, $this->y - 4, 4, 17, $color);
                $num = $entry['number'] === null ? '--' : (string) $entry['number'];
                $second = ($setting['second_in_command_key'] ?? null) === $entry['slot_key']
                    ? '  [2.º mando]' : '';
                $this->text(47, $this->y, $num.'.- '.$entry['name'].$second, 10, true, $color);
                $this->text(305, $this->y, $entry['role'], 8, false, [0.45, 0.49, 0.55]);
                $this->y -= 22;
            }
            $this->y -= 20;
        }

        if (! $any) $this->line('No hay escuadras visibles.', 12);
        $this->finishPage();

        return $this->encodePdf();
    }

    private function newPage(): void
    {
        if ($this->page !== '') $this->finishPage();
        $this->page = '';
        $this->y = 800;
    }

    private function finishPage(): void
    {
        $this->pages[] = $this->page;
        $this->page = '';
    }

    private function requireHeight(float $height): void
    {
        if ($this->y - $height < 50) $this->newPage();
    }

    private function heading(string $name): void
    {
        $this->rectangle(35, $this->y - 9, 525, 26, [0.10, 0.16, 0.24]);
        $this->text(45, $this->y, $name, 12, true, [1, 1, 1]);
        $this->y -= 36;
    }

    private function line(string $value, int $size, bool $bold = false, array $color = [0.12, 0.16, 0.22]): void
    {
        $this->requireHeight($size + 10);
        $this->text(38, $this->y, $value, $size, $bold, $color);
        $this->y -= $size + 11;
    }

    private function rectangle(float $x, float $y, float $w, float $h, array $rgb): void
    {
        $this->page .= sprintf("%.3F %.3F %.3F rg %.1F %.1F %.1F %.1F re f\n", ...[...$rgb, $x, $y, $w, $h]);
    }

    private function text(float $x, float $y, string $content, int $size, bool $bold = false, array $rgb = [0, 0, 0]): void
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT', $content);
        $encoded = $encoded === false ? preg_replace('/[^\x20-\x7E]/', '?', $content) : $encoded;
        $encoded = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $encoded);
        // Prevent long names from running into the right margin.
        $encoded = substr($encoded, 0, $x >= 305 ? 55 : 65);
        $font = $bold ? 'F2' : 'F1';
        $this->page .= sprintf("%.3F %.3F %.3F rg BT /%s %d Tf %.1F %.1F Td (%s) Tj ET\n",
            $rgb[0], $rgb[1], $rgb[2], $font, $size, $x, $y, $encoded);
    }

    private function encodePdf(): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];
        $kids = [];
        $nextId = 5;
        foreach ($this->pages as $page) {
            $pageId = $nextId++;
            $contentId = $nextId++;
            $kids[] = $pageId.' 0 R';
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$contentId.' 0 R >>';
            $objects[$contentId] = '<< /Length '.strlen($page)." >>\nstream\n".$page."\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$object."\nendobj\n";
        }
        $start = strlen($pdf);
        $pdf .= "xref\n0 ".(max(array_keys($objects)) + 1)."\n0000000000 65535 f \n";
        for ($id = 1; $id <= max(array_keys($objects)); $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }
        $pdf .= "trailer\n<< /Size ".(max(array_keys($objects)) + 1)." /Root 1 0 R >>\nstartxref\n".$start."\n%%EOF";
        return $pdf;
    }
}
