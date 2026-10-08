<?php

namespace App\Support;

use App\Models\Page;
use App\Models\PublicNavigationSetting;
use App\Models\Status;
use App\Services\Treasury\TreasuryService;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class PublicNavigation
{
    /** Maximum number of effective top-level columns visible to one audience. */
    public const MAX_TOP_LEVEL_ITEMS = 6;

    /** Defensive cap for the editor payload. The 6-column rule is per audience. */
    public const MAX_CONFIGURED_TOP_LEVEL_ITEMS = 30;

    public const AUDIENCE_GUEST = '__guest__';

    public static function destinationOptions(): array
    {
        return collect(self::destinations())
            ->mapWithKeys(fn (array $destination, string $key): array => [
                $key => $destination['label'],
            ])
            ->all();
    }

    public static function destinations(): array
    {
        $destinations = [
            'home' => ['label' => 'Inicio'],
            'normativa' => ['label' => 'Normativa'],
            'events' => ['label' => 'Eventos'],
            'streams' => ['label' => 'Directos'],
            'activities' => ['label' => 'Actividades'],
            'metopas' => ['label' => 'Metopas'],
            'campaigns' => ['label' => 'Campañas'],
            'organization' => ['label' => 'Organigrama'],
            'users' => ['label' => 'Usuarios'],
            'faqs' => ['label' => 'FAQs'],
            'wiki' => ['label' => 'Wiki', 'external' => true],
            'forum' => ['label' => 'Foro'],
            'roulette' => ['label' => 'Ruleta'],
        ];

        // Filament > Páginas is also a source for the public navigation catalogue.
        // The slug is used instead of the database ID so the saved navigation
        // remains portable between local, staging and production.
        try {
            if (Schema::hasTable('pages')) {
                Page::query()
                    ->select(['title', 'slug', 'template', 'is_published'])
                    ->orderBy('title')
                    ->get()
                    ->each(function (Page $page) use (&$destinations): void {
                        $slug = trim((string) $page->slug);

                        if ($slug === '') {
                            return;
                        }

                        // Normativa and FAQs already have historical fixed
                        // destinations, so avoid showing them twice.
                        if (in_array($slug, ['normativa', 'faqs'], true)) {
                            return;
                        }

                        $destinations['page:' . $slug] = [
                            'label' => trim((string) $page->title) ?: $slug,
                            'dynamic' => true,
                            'slug' => $slug,
                            'published' => (bool) $page->is_published,
                            'template' => (string) ($page->template ?? 'content'),
                        ];
                    });
            }
        } catch (\Throwable) {
            // Keep the header usable during migrations, tests and first boot.
        }

        return $destinations;
    }

    /**
     * Audiences available in the navigation editor.
     *
     * Status names are stored instead of IDs so the JSON remains portable
     * between local, staging and production databases.
     */
    public static function audienceOptions(): array
    {
        $options = [
            self::AUDIENCE_GUEST => 'INVITADO / SIN INICIAR SESIÓN',
        ];

        $fallback = [
            'ACTIVO',
            'CESADO',
            'RECLUTA',
            'BAJA',
            'USUARIO',
            'RESERVA',
        ];

        $statuses = $fallback;

        try {
            if (Schema::hasTable('status')) {
                $databaseStatuses = Status::query()
                    ->orderBy('name')
                    ->pluck('name')
                    ->map(fn ($name): string => strtoupper(trim((string) $name)))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if ($databaseStatuses !== []) {
                    $statuses = $databaseStatuses;
                }
            }
        } catch (\Throwable) {
            // The fallback keeps tests and first-run environments independent
            // from the status table lifecycle.
        }

        foreach ($statuses as $status) {
            $options[$status] = $status;
        }

        return $options;
    }

    public static function defaultItems(): array
    {
        return [
            [
                'type' => 'link',
                'label' => 'Normativa',
                'destination' => 'normativa',
            ],
            [
                'type' => 'link',
                'label' => 'Eventos',
                'destination' => 'events',
            ],
            [
                'type' => 'link',
                'label' => 'Directos',
                'destination' => 'streams',
            ],
            [
                'type' => 'dropdown',
                'label' => 'Comunidad',
                'children' => [
                    ['label' => 'Actividades', 'destination' => 'activities'],
                    ['label' => 'Metopas', 'destination' => 'metopas'],
                    ['label' => 'Campañas', 'destination' => 'campaigns'],
                    ['label' => 'Organigrama', 'destination' => 'organization'],
                    ['label' => 'Wiki', 'destination' => 'wiki'],
                ],
            ],
        ];
    }

    public static function items(): array
    {
        $items = self::defaultItems();

        if (Schema::hasTable('public_navigation_settings')) {
            $stored = PublicNavigationSetting::query()->first()?->items;

            if (is_array($stored)) {
                $items = $stored;
            }
        }

        return self::normalizeItems($items);
    }

    /**
     * Normalizes stored navigation without applying the 6-column rule here.
     * That rule is validated per audience when the editor saves.
     */
    public static function normalizeItems(array $items): array
    {
        $normalized = [];
        $knownDestinations = array_keys(self::destinations());

        foreach (array_slice($items, 0, self::MAX_CONFIGURED_TOP_LEVEL_ITEMS) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $type = $item['type'] ?? null;

            if ($type === 'link') {
                $destination = (string) ($item['destination'] ?? '');

                if (! in_array($destination, $knownDestinations, true)) {
                    continue;
                }

                $normalizedItem = [
                    'type' => 'link',
                    'label' => self::cleanLabel($item['label'] ?? null)
                        ?: self::label($destination),
                    'destination' => $destination,
                ];

                self::copyVisibility($item, $normalizedItem);
                $normalized[] = $normalizedItem;

                continue;
            }

            if ($type === 'external') {
                $label = self::cleanLabel($item['label'] ?? null);
                $url = self::normalizeExternalUrl($item['url'] ?? null);

                if ($label === null || $url === null) {
                    continue;
                }

                $normalizedItem = [
                    'type' => 'external',
                    'label' => $label,
                    'url' => $url,
                ];

                self::copyVisibility($item, $normalizedItem);
                $normalized[] = $normalizedItem;

                continue;
            }

            if ($type !== 'dropdown') {
                continue;
            }

            $children = [];

            foreach (array_slice((array) ($item['children'] ?? []), 0, 20) as $child) {
                if (! is_array($child)) {
                    continue;
                }

                $childType = (string) ($child['type'] ?? 'link');

                if ($childType === 'external') {
                    $label = self::cleanLabel($child['label'] ?? null);
                    $url = self::normalizeExternalUrl($child['url'] ?? null);

                    if ($label === null || $url === null) {
                        continue;
                    }

                    $normalizedChild = [
                        'type' => 'external',
                        'label' => $label,
                        'url' => $url,
                    ];
                } else {
                    $destination = (string) ($child['destination'] ?? '');

                    if (! in_array($destination, $knownDestinations, true)) {
                        continue;
                    }

                    $normalizedChild = [
                        'type' => 'link',
                        'label' => self::cleanLabel($child['label'] ?? null)
                            ?: self::label($destination),
                        'destination' => $destination,
                    ];
                }

                self::copyVisibility($child, $normalizedChild);
                $children[] = $normalizedChild;
            }

            if ($children === []) {
                continue;
            }

            $normalizedItem = [
                'type' => 'dropdown',
                'label' => self::cleanLabel($item['label'] ?? null) ?: 'Menú',
                'children' => $children,
            ];

            self::copyVisibility($item, $normalizedItem);
            $normalized[] = $normalizedItem;
        }

        return $normalized;
    }

    /**
     * Builds the visual editor state. The available collection is a permanent
     * catalogue, not a single-use tray, so pages can be placed more than once.
     *
     * @return array{
     *     menu: array<int, array<string, mixed>>,
     *     available: array<int, array<string, mixed>>
     * }
     */
    public static function editorState(array $items): array
    {
        $audienceKeys = array_keys(self::audienceOptions());
        $menu = [];
        $instance = 0;

        foreach (self::normalizeItems($items) as $item) {
            $instance++;

            if (($item['type'] ?? null) === 'link') {
                $menu[] = [
                    'type' => 'link',
                    'key' => 'link-' . $instance,
                    'destination' => (string) $item['destination'],
                    'label' => (string) $item['label'],
                    'visible_to' => self::editorVisibility($item, $audienceKeys),
                ];

                continue;
            }

            if (($item['type'] ?? null) === 'external') {
                $menu[] = [
                    'type' => 'external',
                    'key' => 'external-' . $instance,
                    'label' => (string) ($item['label'] ?? 'Enlace externo'),
                    'url' => (string) ($item['url'] ?? ''),
                    'visible_to' => self::editorVisibility($item, $audienceKeys),
                ];

                continue;
            }

            if (($item['type'] ?? null) !== 'dropdown') {
                continue;
            }

            $children = [];

            foreach (($item['children'] ?? []) as $child) {
                $instance++;
                $childType = (string) ($child['type'] ?? 'link');

                if ($childType === 'external') {
                    $children[] = [
                        'type' => 'external',
                        'key' => 'external-' . $instance,
                        'label' => (string) ($child['label'] ?? 'Enlace externo'),
                        'url' => (string) ($child['url'] ?? ''),
                        'visible_to' => self::editorVisibility($child, $audienceKeys),
                    ];
                } else {
                    $children[] = [
                        'type' => 'link',
                        'key' => 'link-' . $instance,
                        'destination' => (string) ($child['destination'] ?? ''),
                        'label' => (string) ($child['label'] ?? self::label((string) ($child['destination'] ?? ''))),
                        'visible_to' => self::editorVisibility($child, $audienceKeys),
                    ];
                }
            }

            $menu[] = [
                'type' => 'dropdown',
                'key' => 'dropdown-' . $instance,
                'label' => (string) ($item['label'] ?? 'Menú'),
                'visible_to' => self::editorVisibility($item, $audienceKeys),
                'children' => $children,
            ];
        }

        $available = [];

        foreach (self::destinations() as $destination => $definition) {
            $available[] = [
                'type' => 'link',
                'key' => 'catalog-' . str_replace(':', '-', $destination),
                'destination' => $destination,
                'label' => (string) $definition['label'],
                'source' => ! empty($definition['dynamic']) ? 'dynamic' : 'fixed',
                'published' => (bool) ($definition['published'] ?? true),
            ];
        }

        return [
            'menu' => $menu,
            'available' => $available,
        ];
    }

    /**
     * Validates and converts the drag-and-drop editor payload back into the
     * JSON structure consumed by the public header.
     *
     * Duplicate destinations are intentionally allowed: visibility belongs to
     * each placement, not to the destination itself.
     *
     * @param  array<int, mixed>  $menuItems
     * @param  array<int, mixed>  $availablePages  Kept for Livewire backwards compatibility.
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeEditorState(
        array $menuItems,
        array $availablePages = [],
    ): array {
        if (count($menuItems) > self::MAX_CONFIGURED_TOP_LEVEL_ITEMS) {
            throw new InvalidArgumentException(
                'El editor admite como máximo '
                . self::MAX_CONFIGURED_TOP_LEVEL_ITEMS
                . ' columnas configuradas.',
            );
        }

        $knownDestinations = array_keys(self::destinations());
        $audiences = self::audienceOptions();
        $knownAudiences = array_keys($audiences);
        $normalizedMenu = [];

        foreach ($menuItems as $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException(
                    'La estructura del menú no es válida. Recarga el editor.',
                );
            }

            $type = $item['type'] ?? null;

            if ($type === 'link') {
                $destination = self::validateDestination(
                    $item['destination'] ?? null,
                    $knownDestinations,
                );

                $normalizedMenu[] = [
                    'type' => 'link',
                    'label' => self::cleanLabel($item['label'] ?? null)
                        ?: self::label($destination),
                    'destination' => $destination,
                    'visible_to' => self::validateVisibility(
                        $item['visible_to'] ?? null,
                        $knownAudiences,
                        'La página «' . self::label($destination) . '»',
                    ),
                ];

                continue;
            }

            if ($type === 'external') {
                $label = self::cleanLabel($item['label'] ?? null);

                if ($label === null) {
                    throw new InvalidArgumentException(
                        'Todos los enlaces externos deben tener un nombre.',
                    );
                }

                $url = self::validateExternalUrl($item['url'] ?? null, $label);

                $normalizedMenu[] = [
                    'type' => 'external',
                    'label' => $label,
                    'url' => $url,
                    'visible_to' => self::validateVisibility(
                        $item['visible_to'] ?? null,
                        $knownAudiences,
                        'El enlace externo «' . $label . '»',
                    ),
                ];

                continue;
            }

            if ($type !== 'dropdown') {
                throw new InvalidArgumentException(
                    'Hay una tarjeta de menú no válida. Recarga el editor.',
                );
            }

            $dropdownLabel = self::cleanLabel($item['label'] ?? null);

            if ($dropdownLabel === null) {
                throw new InvalidArgumentException(
                    'Todos los desplegables deben tener un nombre.',
                );
            }

            $rawChildren = $item['children'] ?? [];

            if (! is_array($rawChildren)) {
                throw new InvalidArgumentException(
                    'La estructura del desplegable no es válida.',
                );
            }

            if ($rawChildren === []) {
                throw new InvalidArgumentException(
                    'El desplegable «' . $dropdownLabel
                    . '» debe contener al menos una página.',
                );
            }

            if (count($rawChildren) > 20) {
                throw new InvalidArgumentException(
                    'Un desplegable no puede contener más de 20 páginas.',
                );
            }

            $children = [];

            foreach ($rawChildren as $child) {
                if (! is_array($child)) {
                    throw new InvalidArgumentException(
                        'Hay una página no válida dentro de un desplegable.',
                    );
                }

                $childType = (string) ($child['type'] ?? 'link');

                if ($childType === 'external') {
                    $label = self::cleanLabel($child['label'] ?? null);

                    if ($label === null) {
                        throw new InvalidArgumentException(
                            'Todos los enlaces externos deben tener un nombre.',
                        );
                    }

                    $children[] = [
                        'type' => 'external',
                        'label' => $label,
                        'url' => self::validateExternalUrl($child['url'] ?? null, $label),
                        'visible_to' => self::validateVisibility(
                            $child['visible_to'] ?? null,
                            $knownAudiences,
                            'El enlace externo «' . $label . '»',
                        ),
                    ];

                    continue;
                }

                $destination = self::validateDestination(
                    $child['destination'] ?? null,
                    $knownDestinations,
                );

                $children[] = [
                    'type' => 'link',
                    'label' => self::cleanLabel($child['label'] ?? null)
                        ?: self::label($destination),
                    'destination' => $destination,
                    'visible_to' => self::validateVisibility(
                        $child['visible_to'] ?? null,
                        $knownAudiences,
                        'La página «' . self::label($destination) . '»',
                    ),
                ];
            }

            $normalizedMenu[] = [
                'type' => 'dropdown',
                'label' => $dropdownLabel,
                'visible_to' => self::validateVisibility(
                    $item['visible_to'] ?? null,
                    $knownAudiences,
                    'El desplegable «' . $dropdownLabel . '»',
                ),
                'children' => $children,
            ];
        }

        self::assertAudienceColumnLimits($normalizedMenu, $audiences);

        return $normalizedMenu;
    }

    public static function label(string $destination): string
    {
        return self::destinations()[$destination]['label'] ?? $destination;
    }

    public static function url(string $destination): string
    {
        if (str_starts_with($destination, 'page:')) {
            $slug = trim(substr($destination, strlen('page:')));

            return $slug !== '' ? route('pages.show', $slug) : '#';
        }

        return match ($destination) {
            'home' => route('home'),
            'normativa' => route('pages.show', 'normativa'),
            'events' => route('events.index'),
            'streams' => route('streams.index'),
            'activities' => route('activities.index'),
            'metopas' => route('metopas.index'),
            'campaigns' => route('campaigns.index'),
            'organization' => route('community.organization'),
            'users' => route('users.index'),
            'faqs' => route('pages.show', 'faqs'),
            'wiki' => 'https://wiki.squadalpha.es/',
            'forum' => route('community.forum.home'),
            'roulette' => route('community.roulette.index'),
            default => '#',
        };
    }

    public static function isExternal(string $destination): bool
    {
        return (bool) (self::destinations()[$destination]['external'] ?? false);
    }

    public static function itemUrl(array $item): string
    {
        if (($item['type'] ?? null) === 'external') {
            return self::normalizeExternalUrl($item['url'] ?? null) ?? '#';
        }

        return self::url((string) ($item['destination'] ?? ''));
    }

    public static function itemIsExternal(array $item): bool
    {
        if (($item['type'] ?? null) === 'external') {
            return self::normalizeExternalUrl($item['url'] ?? null) !== null;
        }

        return self::isExternal((string) ($item['destination'] ?? ''));
    }

    public static function canDisplayNavigationItem(array $item): bool
    {
        if (($item['type'] ?? null) === 'external') {
            return self::normalizeExternalUrl($item['url'] ?? null) !== null;
        }

        return self::canDisplayDestination((string) ($item['destination'] ?? ''));
    }

    public static function navigationItemIsActive(array $item): bool
    {
        if (($item['type'] ?? null) === 'external') {
            return false;
        }

        return self::isActive((string) ($item['destination'] ?? ''));
    }

    public static function isActive(string $destination): bool
    {
        if (str_starts_with($destination, 'page:')) {
            $slug = trim(substr($destination, strlen('page:')));

            return request()->routeIs('pages.show')
                && request()->route('page')?->slug === $slug;
        }

        return match ($destination) {
            'home' => request()->routeIs('home'),
            'normativa' => request()->routeIs('pages.show')
                && request()->route('page')?->slug === 'normativa',
            'events' => request()->routeIs('events.*'),
            'streams' => request()->routeIs('streams.*'),
            'activities' => request()->routeIs('activities.*'),
            'metopas' => request()->routeIs('metopas.*'),
            'campaigns' => request()->routeIs('campaigns.*'),
            'organization' => request()->routeIs('community.organization'),
            'users' => request()->routeIs('users.*'),
            'faqs' => request()->routeIs('pages.show')
                && request()->route('page')?->slug === 'faqs',
            'forum' => request()->routeIs('community.forum.*'),
            'roulette' => request()->routeIs('community.roulette.*'),
            default => false,
        };
    }

    /** Backwards-compatible destination check. Access control stays in routes/controllers. */
    public static function canDisplayDestination(string $destination): bool
    {
        $definition = self::destinations()[$destination] ?? null;

        if (! is_array($definition)) {
            return false;
        }

        // Dynamic Filament pages may be prepared in the header before they
        // are published, but they must not produce a public 404 menu link.
        if (! empty($definition['dynamic'])) {
            if (! (bool) ($definition['published'] ?? false)) {
                return false;
            }

            if (($definition['template'] ?? null) === 'treasury') {
                try {
                    return app(TreasuryService::class)->canViewTreasuryPage(auth()->user());
                } catch (\Throwable) {
                    return false;
                }
            }

            return true;
        }

        return true;
    }

    public static function currentAudience(): string
    {
        $user = auth()->user();

        if (! $user) {
            return self::AUDIENCE_GUEST;
        }

        return strtoupper(trim((string) $user->status?->name));
    }

    public static function itemVisibleForAudience(array $item, string $audience): bool
    {
        if (! array_key_exists('visible_to', $item)) {
            return true;
        }

        $visibility = self::normalizeVisibilityList($item['visible_to']);

        return in_array($audience, $visibility, true);
    }

    public static function canDisplayItem(array $item): bool
    {
        return self::itemVisibleForAudience($item, self::currentAudience());
    }

    public static function visibleChildren(array $children): array
    {
        $audience = self::currentAudience();

        return array_values(array_filter(
            $children,
            static fn ($child): bool => is_array($child)
                && self::itemVisibleForAudience($child, $audience)
                && self::canDisplayNavigationItem($child),
        ));
    }

    public static function dropdownDisplayLabel(array $item): string
    {
        return (string) ($item['label'] ?? 'Menú');
    }

    public static function dropdownIsActive(array $children): bool
    {
        foreach ($children as $child) {
            if (is_array($child) && self::navigationItemIsActive($child)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the number of top-level columns that would actually render for
     * an audience. A dropdown only counts if at least one child is visible.
     */
    public static function visibleTopLevelCountForAudience(
        array $items,
        string $audience,
    ): int {
        $count = 0;

        foreach ($items as $item) {
            if (! is_array($item) || ! self::itemVisibleForAudience($item, $audience)) {
                continue;
            }

            if (in_array(($item['type'] ?? null), ['link', 'external'], true)) {
                $count++;
                continue;
            }

            if (($item['type'] ?? null) !== 'dropdown') {
                continue;
            }

            foreach (($item['children'] ?? []) as $child) {
                if (is_array($child) && self::itemVisibleForAudience($child, $audience)) {
                    $count++;
                    break;
                }
            }
        }

        return $count;
    }

    private static function assertAudienceColumnLimits(array $items, array $audiences): void
    {
        $violations = [];

        foreach ($audiences as $audience => $label) {
            $count = self::visibleTopLevelCountForAudience($items, (string) $audience);

            if ($count > self::MAX_TOP_LEVEL_ITEMS) {
                $violations[] = $label . ': ' . $count;
            }
        }

        if ($violations !== []) {
            throw new InvalidArgumentException(
                'Cada estado puede ver como máximo '
                . self::MAX_TOP_LEVEL_ITEMS
                . ' columnas del header. Revisa: '
                . implode(', ', $violations)
                . '.',
            );
        }
    }

    private static function validateDestination(mixed $destination, array $knownDestinations): string
    {
        $destination = is_string($destination) ? trim($destination) : '';

        if (! in_array($destination, $knownDestinations, true)) {
            throw new InvalidArgumentException(
                'Se ha recibido una página no válida. Recarga el editor.',
            );
        }

        return $destination;
    }

    private static function validateExternalUrl(mixed $value, string $label): string
    {
        $url = self::normalizeExternalUrl($value);

        if ($url === null) {
            throw new InvalidArgumentException(
                'El enlace externo «' . $label . '» debe tener una URL http:// o https:// válida.',
            );
        }

        return $url;
    }

    private static function normalizeExternalUrl(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $url = trim($value);

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return mb_substr($url, 0, 2048);
    }

    private static function validateVisibility(
        mixed $visibility,
        array $knownAudiences,
        string $subject,
    ): array {
        if (! is_array($visibility)) {
            throw new InvalidArgumentException(
                $subject . ' debe indicar qué estados pueden verla.',
            );
        }

        $visibility = self::normalizeVisibilityList($visibility);

        foreach ($visibility as $audience) {
            if (! in_array($audience, $knownAudiences, true)) {
                throw new InvalidArgumentException(
                    'Se ha recibido un estado de visibilidad no válido. Recarga el editor.',
                );
            }
        }

        if ($visibility === []) {
            throw new InvalidArgumentException(
                $subject . ' debe ser visible al menos para un estado o para invitados.',
            );
        }

        return $visibility;
    }

    private static function editorVisibility(array $item, array $audienceKeys): array
    {
        if (! array_key_exists('visible_to', $item)) {
            return $audienceKeys;
        }

        $visibility = self::normalizeVisibilityList($item['visible_to']);

        return array_values(array_intersect($audienceKeys, $visibility));
    }

    private static function copyVisibility(array $source, array &$target): void
    {
        if (! array_key_exists('visible_to', $source)) {
            return;
        }

        $target['visible_to'] = self::normalizeVisibilityList($source['visible_to']);
    }

    private static function normalizeVisibilityList(mixed $visibility): array
    {
        if (! is_array($visibility)) {
            return [];
        }

        $normalized = [];

        foreach ($visibility as $audience) {
            if (! is_string($audience)) {
                continue;
            }

            $audience = trim(strip_tags($audience));

            if ($audience === '') {
                continue;
            }

            if ($audience !== self::AUDIENCE_GUEST) {
                $audience = strtoupper($audience);
            }

            $normalized[$audience] = true;
        }

        return array_keys($normalized);
    }

    private static function cleanLabel(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(strip_tags($value));

        return $value !== '' ? mb_substr($value, 0, 80) : null;
    }
}
