<?php

namespace App\Support;

use Illuminate\Support\Str;

class BriefingSectionImages
{
    public const POSITIONS = ['left', 'right', 'top', 'bottom'];

    public const ALIGNMENTS = ['left', 'center', 'right'];

    public const WIDTHS = ['33', '40', '50', '66', '100'];

    /**
     * Normaliza las imágenes guardadas de una sección. También convierte el
     * formato histórico de una única imagen al nuevo formato de galería.
     *
     * @return array<int, array{image: string, image_position: string, image_alignment: string, image_width: string, image_caption: ?string}>
     */
    public static function stored(array $section): array
    {
        $images = is_array($section['images'] ?? null)
            ? $section['images']
            : [];

        if ($images === [] && filled($section['image'] ?? null)) {
            $images = [[
                'image' => $section['image'],
                'image_position' => $section['image_position'] ?? 'left',
                'image_alignment' => $section['image_alignment'] ?? 'left',
                'image_width' => $section['image_width'] ?? '40',
                'image_caption' => $section['image_caption'] ?? null,
            ]];
        }

        return collect($images)
            ->filter(fn ($image): bool => is_array($image))
            ->map(function (array $image): ?array {
                $reference = BriefingMarkup::normalizeImageReference(
                    $image['image'] ?? null
                );

                if ($reference === null) {
                    return null;
                }

                $position = (string) ($image['image_position'] ?? 'left');
                $alignment = (string) ($image['image_alignment'] ?? 'left');
                $width = (string) ($image['image_width'] ?? '40');
                $caption = trim((string) ($image['image_caption'] ?? ''));

                if (! in_array($position, self::POSITIONS, true)) {
                    $position = 'left';
                }

                if (! in_array($alignment, self::ALIGNMENTS, true)) {
                    $alignment = 'left';
                }

                if (! in_array($width, self::WIDTHS, true)) {
                    $width = '40';
                }

                if ($width === '100' && in_array($position, ['left', 'right'], true)) {
                    $position = 'top';
                }

                return [
                    'image' => $reference,
                    'image_position' => $position,
                    'image_alignment' => $alignment,
                    'image_width' => $width,
                    'image_caption' => $caption !== '' ? $caption : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{image: string, image_position: string, image_alignment: string, image_width: string, image_caption: ?string}>
     */
    public static function forDisplay(array $section): array
    {
        return collect(self::stored($section))
            ->map(function (array $image): array {
                $image['image'] = BriefingMarkup::imageUrl($image['image']) ?? '';

                return $image;
            })
            ->filter(fn (array $image): bool => filled($image['image']))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forEditor(array $section): array
    {
        return collect(self::stored($section))
            ->map(function (array $image): array {
                $reference = (string) $image['image'];
                $isRemote = Str::startsWith(strtolower($reference), ['http://', 'https://']);

                return [
                    'image_upload' => $isRemote ? null : $reference,
                    'legacy_image' => $isRemote ? $reference : null,
                    'remove_legacy_image' => false,
                    'image_position' => $image['image_position'],
                    'image_alignment' => $image['image_alignment'],
                    'image_width' => $image['image_width'],
                    'image_caption' => $image['image_caption'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Convierte una fila del repeater de Filament al formato persistente.
     */
    public static function fromEditor(array $image): ?array
    {
        $uploaded = BriefingMarkup::normalizeImageReference(
            $image['image_upload'] ?? null
        );
        $legacy = BriefingMarkup::normalizeImageReference(
            $image['legacy_image'] ?? null
        );

        $reference = $uploaded;

        if (
            $reference === null
            && ! (bool) ($image['remove_legacy_image'] ?? false)
        ) {
            $reference = $legacy;
        }

        if ($reference === null) {
            return null;
        }

        $position = (string) ($image['image_position'] ?? 'left');
        $alignment = (string) ($image['image_alignment'] ?? 'left');
        $width = (string) ($image['image_width'] ?? '40');
        $caption = trim((string) ($image['image_caption'] ?? ''));

        if (! in_array($position, self::POSITIONS, true)) {
            $position = 'left';
        }

        if (! in_array($alignment, self::ALIGNMENTS, true)) {
            $alignment = 'left';
        }

        if (! in_array($width, self::WIDTHS, true)) {
            $width = '40';
        }

        if ($width === '100' && in_array($position, ['left', 'right'], true)) {
            $position = 'top';
        }

        return [
            'image' => $reference,
            'image_position' => $position,
            'image_alignment' => $alignment,
            'image_width' => $width,
            'image_caption' => $caption !== '' ? $caption : null,
        ];
    }
}
