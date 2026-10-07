<?php

namespace App\Services\MemberProcedures;

use App\Models\Event;
use App\Models\EventStatus;
use App\Models\MemberProcedureSetting;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use RuntimeException;

class WeeklyActivityTelegramService
{
    public function __construct(
        private readonly TelegramService $telegram,
        private readonly TelegramNotificationService $messages,
    ) {
    }

    /** @return array<string, mixed> */
    public function window(?CarbonInterface $at = null): array
    {
        $now = CarbonImmutable::instance($at ?? now())->setTimezone(config('app.timezone'));
        $isSunday = $now->isSunday();
        $isMonday = $now->isMonday();

        $open = $isSunday
            || ($isMonday && $now->lt($now->startOfDay()->addHours(18)));

        if ($isSunday) {
            $weekStart = $now->addDay()->startOfDay();
            $windowStart = $now->startOfDay();
            $windowEnd = $weekStart->addHours(18);
        } elseif ($isMonday && $now->lt($now->startOfDay()->addHours(18))) {
            $weekStart = $now->startOfDay();
            $windowStart = $weekStart->subDay()->startOfDay();
            $windowEnd = $weekStart->addHours(18);
        } else {
            $weekStart = $now->next(CarbonInterface::MONDAY)->startOfDay();
            $windowStart = $weekStart->subDay()->startOfDay();
            $windowEnd = $weekStart->addHours(18);
        }

        return [
            'open' => $open,
            'week_start' => $weekStart,
            'week_end' => $weekStart->addDays(5)->endOfDay(),
            'window_start' => $windowStart,
            'window_end' => $windowEnd,
            'label' => $open
                ? 'Disponible hasta el lunes ' . $windowEnd->format('d/m/Y') . ' a las 18:00.'
                : 'Disponible desde el domingo ' . $windowStart->format('d/m/Y') . ' a las 00:00 hasta el lunes a las 18:00.',
        ];
    }

    /** @return array<string, mixed> */
    public function preview(MemberProcedureSetting $setting, ?CarbonInterface $at = null, ?string $preferredEnding = null): array
    {
        $window = $this->window($at);
        /** @var CarbonImmutable $weekStart */
        $weekStart = $window['week_start'];
        /** @var CarbonImmutable $weekEnd */
        $weekEnd = $window['week_end'];

        $blocking = [];
        $warnings = [];

        $activeStatusId = (int) ($setting->telegram_weekly_active_event_status_id ?? 0);
        $activeStatus = $activeStatusId > 0 ? EventStatus::query()->find($activeStatusId) : null;
        if (! $activeStatus) {
            $blocking[] = 'Configura en Filament qué estado de evento representa ACTIVO para la actividad semanal.';
        }

        $requiredDays = collect($setting->telegram_weekly_required_weekdays ?? [2, 5])
            ->map(fn ($day): int => (int) $day)
            ->filter(fn (int $day): bool => $day >= 1 && $day <= 6)
            ->unique()
            ->sort()
            ->values();

        if ($requiredDays->isEmpty()) {
            $blocking[] = 'Selecciona al menos un día obligatorio para la actividad semanal.';
        }

        $requiredTypeIds = collect($setting->telegram_weekly_required_activity_type_ids ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($requiredTypeIds->isEmpty()) {
            $blocking[] = 'Selecciona al menos un tipo de actividad obligatorio, por ejemplo OPERATIVO.';
        }

        $events = Event::query()
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->with(['eventStatus', 'activity.activityType'])
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $days = [];
        for ($day = 1; $day <= 6; $day++) {
            $date = $weekStart->addDays($day - 1);
            $dayEvents = $events->filter(fn (Event $event): bool => $event->date?->toDateString() === $date->toDateString())->values();
            $activeEvents = $activeStatus
                ? $dayEvents->filter(fn (Event $event): bool => (int) $event->event_status_id === (int) $activeStatus->id)->values()
                : collect();
            $required = $requiredDays->contains($day);

            if ($dayEvents->count() > $activeEvents->count() && $activeStatus) {
                $inactiveCount = $dayEvents->count() - $activeEvents->count();
                $warnings[] = $this->dayLabel($day) . ': ' . $inactiveCount . ' actividad(es) no están en estado '
                    . $activeStatus->name . ' y no se incluirán en el mensaje.';
            }

            if ($required) {
                $validRequired = $activeEvents->filter(function (Event $event) use ($requiredTypeIds): bool {
                    $typeId = (int) ($event->activity?->activity_type_id ?? 0);

                    return $requiredTypeIds->contains($typeId);
                });

                if ($validRequired->isEmpty()) {
                    $blocking[] = $this->dayLabel($day) . ': falta una actividad obligatoria en estado '
                        . ($activeStatus?->name ?: 'ACTIVO') . ' y del tipo configurado.';
                }
            } else {
                if ($dayEvents->isEmpty()) {
                    $warnings[] = $this->dayLabel($day) . ': no hay ninguna actividad programada.';
                } elseif ($activeEvents->isEmpty()) {
                    $statuses = $dayEvents->pluck('eventStatus.name')->filter()->unique()->implode(', ');
                    $warnings[] = $this->dayLabel($day) . ': hay actividad, pero no está en el estado '
                        . ($activeStatus?->name ?: 'ACTIVO')
                        . ($statuses !== '' ? ' (estado actual: ' . $statuses . ')' : '') . '.';
                }
            }

            $days[] = [
                'iso_weekday' => $day,
                'label' => $this->dayLabel($day),
                'date' => $date->format('d/m/Y'),
                'required' => $required,
                'events' => $dayEvents->map(fn (Event $event): array => [
                    'id' => (int) $event->id,
                    'name' => (string) $event->name,
                    'time' => $event->date?->format('H:i') ?? '',
                    'status' => (string) ($event->eventStatus?->name ?? 'Sin estado'),
                    'activity_type' => (string) ($event->activity?->activityType?->name ?? 'Sin tipo'),
                    'active' => $activeStatus ? (int) $event->event_status_id === (int) $activeStatus->id : false,
                    'url' => route('events.show', $event),
                ])->all(),
            ];
        }

        $activeEvents = $activeStatus
            ? $events->filter(fn (Event $event): bool => (int) $event->event_status_id === (int) $activeStatus->id)->values()
            : collect();

        $ending = $preferredEnding !== null
            ? $preferredEnding
            : $this->chooseEnding($setting->telegram_weekly_endings ?? []);

        $message = $this->buildMessage($setting, $activeEvents, $requiredDays, $weekStart, $weekEnd, $ending);

        return [
            'window_open' => (bool) $window['open'],
            'window_label' => (string) $window['label'],
            'week_start' => $weekStart->toIso8601String(),
            'week_end' => $weekEnd->toIso8601String(),
            'week_label' => $weekStart->format('d/m/Y') . ' – ' . $weekEnd->format('d/m/Y'),
            'active_status' => $activeStatus?->name,
            'days' => $days,
            'blocking_errors' => array_values(array_unique($blocking)),
            'warnings' => array_values(array_unique($warnings)),
            'can_send' => (bool) $window['open'] && $blocking === [],
            'message' => $message,
            'ending' => $ending,
        ];
    }


    /** @return array{message:string,ending:string} */
    public function previewTemplateSample(MemberProcedureSetting $setting, ?string $preferredEnding = null): array
    {
        $requiredDays = collect($setting->telegram_weekly_required_weekdays ?? [2, 5])
            ->map(fn ($day): int => (int) $day)
            ->filter(fn (int $day): bool => $day >= 1 && $day <= 6)
            ->unique()
            ->values();

        $sampleEvents = [
            ['day' => 1, 'line' => 'Lunes 28/09/26 20:00H', 'name' => 'Lunes de prácticas', 'url' => 'https://www.squadalpha.es/eventos/412'],
            ['day' => 2, 'line' => 'Martes 29/09/26 20:00H', 'name' => 'OPERATION ICE FREYA', 'url' => 'https://www.squadalpha.es/eventos/401'],
            ['day' => 5, 'line' => 'Viernes 02/10/26 22:30H', 'name' => 'OPERATION WATCHTOWER. CAP1', 'url' => 'https://www.squadalpha.es/eventos/146'],
        ];

        $blocks = collect($sampleEvents)->map(function (array $event) use ($requiredDays): string {
            $dateLine = $this->messages->escapeMarkdownV2((string) $event['line']);
            if ($requiredDays->contains((int) $event['day'])) {
                $dateLine = '*' . $dateLine . '*';
            }

            $name = $this->messages->escapeMarkdownV2((string) $event['name']);
            $url = (string) $event['url'];

            return $dateLine . "\n" . $name . "\n" . $this->messages->markdownLink($url, $url);
        })->implode("\n\n");

        $ending = $preferredEnding ?? $this->chooseEnding($setting->telegram_weekly_endings ?? []);
        $template = trim((string) $setting->telegram_weekly_template);
        if ($template === '') {
            $template = "ACTUALIZADA ACTIVIDAD SEMANAL\n\n☠️☠️ ACTIVIDAD SEMANAL ☠️☠️\n\n{{eventos}}\n\n{{cierre}}";
        }

        $hasClosingPlaceholder = str_contains($template, '{{cierre}}');
        $message = strtr($template, [
            '{{eventos}}' => $blocks,
            '{{semana_inicio}}' => $this->messages->escapeMarkdownV2('28/09/2026'),
            '{{semana_fin}}' => $this->messages->escapeMarkdownV2('03/10/2026'),
            '{{cierre}}' => $ending,
        ]);

        if (! $hasClosingPlaceholder && $ending !== '') {
            $message .= "\n\n" . $ending;
        }

        $message = preg_replace("/\n{3,}/", "\n\n", $message) ?? $message;

        return ['message' => trim($message), 'ending' => $ending];
    }

    /** @return array<string, mixed> */
    public function send(MemberProcedureSetting $setting, ?CarbonInterface $at = null, ?string $preferredEnding = null): array
    {
        if (! $this->telegram->isConfigured()) {
            throw new RuntimeException('Telegram no está configurado. Revisa TELEGRAM_ENABLED y TELEGRAM_BOT_TOKEN.');
        }
        if (blank($setting->telegram_network_chat_id)) {
            throw new RuntimeException('Configura primero el chat de = ALPHA FORCE NETWORK =.');
        }

        $preview = $this->preview($setting, $at, $preferredEnding);
        if (! $preview['window_open']) {
            throw new RuntimeException('El envío de actividad semanal solo está disponible desde el domingo a las 00:00 hasta el lunes a las 18:00.');
        }
        if ($preview['blocking_errors'] !== []) {
            throw new RuntimeException(implode(' ', $preview['blocking_errors']));
        }

        $result = $this->telegram->sendMessage(
            (string) $setting->telegram_network_chat_id,
            (string) $preview['message'],
            'MarkdownV2',
        );

        $result['week_start'] = $preview['week_start'];
        $result['week_end'] = $preview['week_end'];
        $result['message_kind'] = 'weekly_activity';

        return $result;
    }

    private function buildMessage(
        MemberProcedureSetting $setting,
        Collection $events,
        Collection $requiredDays,
        CarbonImmutable $weekStart,
        CarbonImmutable $weekEnd,
        string $ending,
    ): string {
        $blocks = [];

        foreach ($events as $event) {
            /** @var Event $event */
            if (! $event->date) {
                continue;
            }

            $day = (int) $event->date->isoWeekday();
            $dayName = $this->dayLabel($day);
            $dateLine = $dayName . ' ' . $event->date->format('d/m/y H:i') . 'H';
            $dateLine = $this->messages->escapeMarkdownV2($dateLine);

            if ($requiredDays->contains($day)) {
                $dateLine = '*' . $dateLine . '*';
            }

            $name = $this->messages->escapeMarkdownV2((string) $event->name);
            $url = route('events.show', $event);
            $link = $this->messages->markdownLink($url, $url);

            $blocks[] = $dateLine . "\n" . $name . "\n" . $link;
        }

        $eventsText = implode("\n\n", $blocks);
        $template = trim((string) $setting->telegram_weekly_template);
        if ($template === '') {
            $template = "ACTUALIZADA ACTIVIDAD SEMANAL\n\n☠️☠️ ACTIVIDAD SEMANAL ☠️☠️\n\n{{eventos}}\n\n{{cierre}}";
        }

        $hasClosingPlaceholder = str_contains($template, '{{cierre}}');
        $message = strtr($template, [
            '{{eventos}}' => $eventsText,
            '{{semana_inicio}}' => $this->messages->escapeMarkdownV2($weekStart->format('d/m/Y')),
            '{{semana_fin}}' => $this->messages->escapeMarkdownV2($weekEnd->format('d/m/Y')),
            '{{cierre}}' => $ending,
        ]);

        if (! $hasClosingPlaceholder && $ending !== '') {
            $message .= "\n\n" . $ending;
        }

        $message = preg_replace("/\n{3,}/", "\n\n", $message) ?? $message;

        return trim($message);
    }

    /** @param array<int, mixed> $endings */
    private function chooseEnding(array $endings): string
    {
        $values = collect($endings)
            ->map(function ($item): string {
                if (is_array($item)) {
                    return trim((string) ($item['text'] ?? ''));
                }

                return trim((string) $item);
            })
            ->filter()
            ->values();

        if ($values->isEmpty()) {
            return '';
        }

        return (string) $values->get(random_int(0, $values->count() - 1));
    }

    private function dayLabel(int $isoWeekday): string
    {
        return [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
        ][$isoWeekday] ?? 'Día';
    }
}
