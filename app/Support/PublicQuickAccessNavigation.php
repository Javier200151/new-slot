<?php

namespace App\Support;

use App\Models\PublicQuickAccessSetting;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/** Independent quick-access navigation. No header/footer item limitations. */
class PublicQuickAccessNavigation
{
    public const MAX_ITEMS = 12;

    public static function icons(): array
    {
        return QuickAccessIcons::NAMES;
    }

    public static function defaultItems(): array
    {
        return [
            ['type' => 'link', 'destination' => 'users', 'label' => 'Usuarios', 'icon' => 'users'],
            ['type' => 'link', 'destination' => 'events', 'label' => 'Eventos', 'icon' => 'calendar-days'],
            ['type' => 'link', 'destination' => 'wiki', 'label' => 'Wiki', 'icon' => 'book-open'],
            ['type' => 'scroll_top', 'label' => 'Volver arriba', 'icon' => 'arrow-up'],
        ];
    }

    public static function catalog(): array
    {
        $items = [];
        foreach (PublicNavigation::destinations() as $destination => $definition) {
            $items[] = ['type' => 'link', 'destination' => $destination, 'label' => $definition['label'],
                'published' => (bool) ($definition['published'] ?? true)];
        }
        $items[] = ['type' => 'server_status', 'label' => 'Estado de servidores'];
        $items[] = ['type' => 'scroll_top', 'label' => 'Volver arriba'];
        return $items;
    }

    public static function settings(): array
    {
        $defaults = ['items' => self::defaultItems(), 'position' => 'right', 'is_enabled' => true];
        try {
            if (! Schema::hasTable('public_quick_access_settings')) {
                return $defaults;
            }
            $stored = PublicQuickAccessSetting::query()->first();
            if (! $stored) {
                return $defaults;
            }
            return [
                'items' => self::normalizeStored((array) $stored->items),
                'position' => in_array($stored->position, ['left', 'right'], true) ? $stored->position : 'right',
                'is_enabled' => (bool) $stored->is_enabled,
            ];
        } catch (\Throwable) {
            return $defaults;
        }
    }

    public static function normalizeStored(array $items): array
    {
        $normalized = [];
        $destinations = array_keys(PublicNavigation::destinations());
        $icons = self::icons();

        $serverStatusSeen = false;
        foreach (array_slice($items, 0, self::MAX_ITEMS) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $type = $item['type'] ?? null;
            $icon = $item['icon'] ?? null;
            if (! is_string($icon) || ! in_array($icon, $icons, true)) {
                continue;
            }
            $label = self::cleanLabel($item['label'] ?? null);
            if ($type === 'link' && in_array($item['destination'] ?? null, $destinations, true)) {
                $destination = $item['destination'];
                $entry = ['type' => 'link', 'destination' => $destination,
                    'label' => $label ?? PublicNavigation::label($destination), 'icon' => $icon];
            } elseif ($type === 'external' && ($url = self::externalUrl($item['url'] ?? null)) && $label) {
                $entry = ['type' => 'external', 'url' => $url, 'label' => $label, 'icon' => $icon];
            } elseif ($type === 'server_status' && ! $serverStatusSeen) {
                $serverStatusSeen = true;
                $entry = ['type' => 'server_status', 'label' => $label ?? 'Estado de servidores', 'icon' => $icon];
            } elseif ($type === 'scroll_top') {
                $entry = ['type' => 'scroll_top', 'label' => $label ?? 'Volver arriba', 'icon' => $icon];
            } else {
                continue;
            }
            if (isset($item['visible_to']) && is_array($item['visible_to'])) {
                $entry['visible_to'] = array_values(array_intersect(array_unique($item['visible_to']), array_keys(PublicNavigation::audienceOptions())));
            }
            $normalized[] = $entry;
        }
        return $normalized;
    }

    public static function validateAndNormalize(array $items): array
    {
        if (count($items) > self::MAX_ITEMS) {
            throw new InvalidArgumentException('La barra admite un máximo de 12 iconos.');
        }
        $audiences = array_keys(PublicNavigation::audienceOptions());
        $knownDestinations = array_keys(PublicNavigation::destinations());
        $icons = self::icons();
        $scrollTopCount = 0;
        $serverStatusCount = 0;
        $result = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException('Acceso rápido no válido.');
            }
            $icon = $item['icon'] ?? null;
            if (! is_string($icon) || ! in_array($icon, $icons, true)) {
                throw new InvalidArgumentException('Selecciona un icono del catálogo disponible.');
            }
            $label = self::cleanLabel($item['label'] ?? null);
            if (! $label) {
                throw new InvalidArgumentException('Indica un nombre accesible para el icono.');
            }
            $type = $item['type'] ?? null;
            $entry = ['type' => $type, 'label' => $label, 'icon' => $icon];
            if ($type === 'link') {
                $destination = $item['destination'] ?? null;
                if (! is_string($destination) || ! in_array($destination, $knownDestinations, true)) {
                    throw new InvalidArgumentException('El destino seleccionado no existe.');
                }
                $entry['destination'] = $destination;
            } elseif ($type === 'external') {
                $url = self::externalUrl($item['url'] ?? null);
                if (! $url) {
                    throw new InvalidArgumentException('El enlace externo necesita una URL http(s) válida.');
                }
                $entry['url'] = $url;
            } elseif ($type === 'server_status') {
                if (++$serverStatusCount > 1) {
                    throw new InvalidArgumentException('Estado de servidores solo puede colocarse una vez en los accesos rápidos.');
                }
            } elseif ($type === 'scroll_top') {
                if (++$scrollTopCount > 1) {
                    throw new InvalidArgumentException('Solo puede existir un botón «Volver arriba».');
                }
            } else {
                throw new InvalidArgumentException('Solo se admiten botones, Estado de servidores y Volver arriba.');
            }
            $visible = $item['visible_to'] ?? $audiences;
            if (! is_array($visible) || $visible === [] ||
                count(array_filter($visible, 'is_string')) !== count($visible) ||
                array_diff($visible, $audiences)) {
                throw new InvalidArgumentException('Selecciona al menos un estado válido por acceso.');
            }
            $entry['visible_to'] = array_values(array_unique($visible));
            $result[] = $entry;
        }
        return $result;
    }

    public static function editorItems(array $items): array
    {
        return array_map(static fn (array $item, int $index): array => $item + [
            'key' => 'quick-' . $index,
            'visible_to' => array_keys(PublicNavigation::audienceOptions()),
        ], $items, array_keys($items));
    }

    public static function visibleItems(array $items): array
    {
        $audience = PublicNavigation::currentAudience();
        return array_values(array_filter(self::normalizeStored($items), static function (array $item) use ($audience): bool {
            if (! PublicNavigation::itemVisibleForAudience($item, $audience)) {
                return false;
            }
            if ($item['type'] === 'server_status') {
                // La ruta de infraestructura solo permite ACTIVO y RECLUTA.
                return in_array($audience, ['ACTIVO', 'RECLUTA'], true);
            }
            return $item['type'] === 'scroll_top' || PublicNavigation::canDisplayNavigationItem($item);
        }));
    }

    private static function cleanLabel(mixed $label): ?string
    {
        if (! is_string($label)) return null;
        $label = trim(strip_tags($label));
        return $label === '' ? null : mb_substr($label, 0, 80);
    }

    private static function externalUrl(mixed $value): ?string
    {
        if (! is_string($value) || ! filter_var($value, FILTER_VALIDATE_URL) ||
            ! in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return null;
        }
        return mb_substr(trim($value), 0, 2048);
    }
}
