<?php

namespace App\Support;

use App\Models\PublicFooterSetting;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/** Independiente del header: solo botones y el widget fijo Estado de servidores. */
class PublicFooterNavigation
{
    public const MAX_ITEMS = 24;

    public static function defaultItems(): array
    {
        return [
            ['type' => 'link', 'label' => 'Usuarios', 'destination' => 'users'],
            ['type' => 'server_status', 'label' => 'Estado', 'visible_to' => ['ACTIVO', 'RECLUTA']],
        ];
    }

    public static function catalog(): array
    {
        $options = [];

        foreach (PublicNavigation::destinations() as $key => $definition) {
            $options[] = [
                'type' => 'link',
                'destination' => $key,
                'label' => $definition['label'],
                'published' => (bool) ($definition['published'] ?? true),
            ];
        }

        $options[] = ['type' => 'server_status', 'label' => 'Estado de servidores'];

        return $options;
    }

    /** Loading a missing table does not interrupt the public website during deployment. */
    public static function items(): array
    {
        $items = self::defaultItems();

        try {
            if (Schema::hasTable('public_footer_settings')) {
                $stored = PublicFooterSetting::query()->first()?->items;
                if (is_array($stored)) {
                    $items = $stored;
                }
            }
        } catch (\Throwable) {
            return self::defaultItems();
        }

        return self::normalizeStored($items);
    }

    public static function normalizeStored(array $items): array
    {
        $result = [];
        $known = array_keys(PublicNavigation::destinations());

        foreach (array_slice($items, 0, self::MAX_ITEMS) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $type = $item['type'] ?? null;
            $label = self::label($item['label'] ?? null);

            if ($type === 'server_status') {
                $normalized = ['type' => 'server_status', 'label' => $label ?: 'Estado'];
            } elseif ($type === 'link' && in_array($item['destination'] ?? null, $known, true)) {
                $destination = (string) $item['destination'];
                $normalized = ['type' => 'link', 'destination' => $destination,
                    'label' => $label ?: PublicNavigation::label($destination)];
            } elseif ($type === 'external' && ($url = self::externalUrl($item['url'] ?? null)) !== null && $label !== null) {
                $normalized = ['type' => 'external', 'url' => $url, 'label' => $label];
            } else {
                continue;
            }

            if (array_key_exists('visible_to', $item) && is_array($item['visible_to'])) {
                $normalized['visible_to'] = array_values(array_unique(array_filter($item['visible_to'], 'is_string')));
            }

            $result[] = $normalized;
        }

        return $result;
    }

    public static function editorItems(array $items): array
    {
        return array_map(static fn (array $item, int $i): array => $item + [
            'key' => 'footer-' . $i,
            'visible_to' => array_keys(PublicNavigation::audienceOptions()),
        ], self::normalizeStored($items), array_keys(self::normalizeStored($items)));
    }

    public static function validateAndNormalize(array $items): array
    {
        if (count($items) > self::MAX_ITEMS) {
            throw new InvalidArgumentException('El footer admite hasta ' . self::MAX_ITEMS . ' botones.');
        }

        $known = array_keys(PublicNavigation::destinations());
        $audiences = array_keys(PublicNavigation::audienceOptions());
        $result = [];
        $serverCount = 0;

        foreach ($items as $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException('Hay un elemento del footer no válido.');
            }

            $type = (string) ($item['type'] ?? '');
            $label = self::label($item['label'] ?? null);

            if ($type === 'server_status') {
                $serverCount++;
                if ($serverCount > 1) {
                    throw new InvalidArgumentException('Estado de servidores solo puede colocarse una vez.');
                }
                $normalized = ['type' => 'server_status', 'label' => $label ?: 'Estado'];
            } elseif ($type === 'link') {
                $destination = $item['destination'] ?? null;
                if (! is_string($destination) || ! in_array($destination, $known, true)) {
                    throw new InvalidArgumentException('Una página del footer ya no existe.');
                }
                $normalized = ['type' => 'link', 'destination' => $destination,
                    'label' => $label ?: PublicNavigation::label($destination)];
            } elseif ($type === 'external') {
                $url = self::externalUrl($item['url'] ?? null);
                if ($label === null || $url === null) {
                    throw new InvalidArgumentException('El enlace externo necesita nombre y una URL http(s) válida.');
                }
                $normalized = ['type' => 'external', 'label' => $label, 'url' => $url];
            } else {
                // No se permiten desplegables de páginas.
                throw new InvalidArgumentException('El footer solo admite botones individuales.');
            }

            $visible = $item['visible_to'] ?? $audiences;
            if (! is_array($visible) || $visible === []
                || count(array_filter($visible, 'is_string')) !== count($visible)
                || array_diff($visible, $audiences)) {
                throw new InvalidArgumentException('Selecciona al menos un estado válido para cada botón.');
            }
            $normalized['visible_to'] = array_values(array_unique($visible));
            $result[] = $normalized;
        }

        return $result;
    }

    public static function visibleItems(): array
    {
        $audience = PublicNavigation::currentAudience();
        $canSeeServers = in_array($audience, ['ACTIVO', 'RECLUTA'], true);

        return array_values(array_filter(self::items(), static function (array $item) use ($audience, $canSeeServers): bool {
            if (! PublicNavigation::itemVisibleForAudience($item, $audience)) {
                return false;
            }
            if ($item['type'] === 'server_status') {
                return $canSeeServers;
            }
            return PublicNavigation::canDisplayNavigationItem($item);
        }));
    }

    private static function label(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim(strip_tags($value));
        return $value === '' ? null : mb_substr($value, 0, 80);
    }

    private static function externalUrl(mixed $value): ?string
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }
        if (! in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return null;
        }
        return mb_substr(trim($value), 0, 2048);
    }
}
