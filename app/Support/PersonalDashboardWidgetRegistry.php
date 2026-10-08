<?php

namespace App\Support;

use App\Filament\Pages\EventCalendar;
use App\Filament\Widgets\PendingRecruitmentDismissalsWidget;
use App\Filament\Widgets\PendingRecruitmentPromotionsWidget;
use App\Filament\Widgets\PendingVeteranciesWidget;
use App\Filament\Widgets\RecruitmentApplicationsDashboardWidget;
use App\Filament\Widgets\UpcomingVeteranciesWidget;

class PersonalDashboardWidgetRegistry
{
    public const RECRUITMENT_APPROVED = 'recruitment-approved';
    public const RECRUITMENT_PROMOTIONS = 'recruitment-promotions';
    public const RECRUITMENT_DISMISSALS = 'recruitment-dismissals';
    public const UPCOMING_VETERANCIES = 'upcoming-veterancies';
    public const PENDING_VETERANCIES = 'pending-veterancies';
    public const MINI_CALENDAR = 'mini-calendar';
    public const REMINDERS = 'reminders';
    public const QUICK_LINKS = 'quick-links';
    public const QUICK_SEARCH = 'quick-search';
    public const PROCEDURE_NOTIFICATIONS = 'procedure-notifications';

    public const GRID_COLUMNS = 4;
    public const GRID_ROWS_MAX = 4;

    /**
     * min_size/max_size expresan [ancho, alto] en celdas de la rejilla.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            self::RECRUITMENT_APPROVED => [
                'label' => 'Alistados aprobados',
                'description' => 'Solicitudes aprobadas que todavía deben entrar en reclutamiento.',
                'component' => RecruitmentApplicationsDashboardWidget::class,
                'default_size' => '2x2',
                'min_size' => [2, 2],
                'max_size' => [4, 4],
                'configurable' => false,
            ],
            self::RECRUITMENT_PROMOTIONS => [
                'label' => 'Reclutas pendientes de promocionar',
                'description' => 'Reclutas que requieren una promoción administrativa.',
                'component' => PendingRecruitmentPromotionsWidget::class,
                'default_size' => '2x2',
                'min_size' => [2, 2],
                'max_size' => [4, 4],
                'configurable' => false,
            ],
            self::RECRUITMENT_DISMISSALS => [
                'label' => 'Reclutas pendientes de baja',
                'description' => 'Reclutas cuyo proceso está pendiente de baja.',
                'component' => PendingRecruitmentDismissalsWidget::class,
                'default_size' => '2x2',
                'min_size' => [2, 2],
                'max_size' => [4, 4],
                'configurable' => false,
            ],
            self::UPCOMING_VETERANCIES => [
                'label' => 'Próximas veteranías',
                'description' => 'Miembros que alcanzarán una veteranía próximamente.',
                'component' => UpcomingVeteranciesWidget::class,
                'default_size' => '2x2',
                'min_size' => [2, 2],
                'max_size' => [4, 4],
                'configurable' => false,
            ],
            self::PENDING_VETERANCIES => [
                'label' => 'Veteranías pendientes de aprobar',
                'description' => 'Veteranías alcanzadas que esperan aprobación administrativa.',
                'component' => PendingVeteranciesWidget::class,
                'default_size' => '2x2',
                'min_size' => [2, 2],
                'max_size' => [4, 4],
                'configurable' => false,
            ],
            self::MINI_CALENDAR => [
                'label' => 'Mini calendario',
                'description' => 'Vista compacta del calendario administrativo con eventos y reservas.',
                'component' => null,
                'default_size' => '4x3',
                'min_size' => [3, 3],
                'max_size' => [4, 4],
                'configurable' => false,
            ],
            self::REMINDERS => [
                'label' => 'Recordatorios',
                'description' => 'Bloc de texto personal asociado a este dashboard.',
                'component' => null,
                'default_size' => '2x2',
                'min_size' => [1, 2],
                'max_size' => [4, 4],
                'configurable' => false,
            ],
            self::QUICK_LINKS => [
                'label' => 'Accesos rápidos',
                'description' => 'Tus enlaces frecuentes dentro o fuera de Squad ALPHA.',
                'component' => null,
                'default_size' => '2x1',
                'min_size' => [1, 1],
                'max_size' => [4, 4],
                'configurable' => true,
            ],
            self::QUICK_SEARCH => [
                'label' => 'Búsqueda rápida',
                'description' => 'Busca directamente miembros, eventos e hilos del foro.',
                'component' => null,
                'default_size' => '2x2',
                'min_size' => [2, 1],
                'max_size' => [4, 4],
                'configurable' => false,
            ],
            self::PROCEDURE_NOTIFICATIONS => [
                'label' => 'Avisos de procedimientos',
                'description' => 'Avisos pendientes dirigidos a ti o a tus grupos SQA, como Tesorería o coordinación de tutores.',
                'component' => null,
                'default_size' => '2x2',
                'min_size' => [2, 2],
                'max_size' => [4, 4],
                'configurable' => false,
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public static function definition(string $type): ?array
    {
        return self::definitions()[$type] ?? null;
    }

    /** @return list<string> */
    public static function defaultTypes(): array
    {
        return [
            self::RECRUITMENT_APPROVED,
            self::RECRUITMENT_PROMOTIONS,
            self::RECRUITMENT_DISMISSALS,
            self::UPCOMING_VETERANCIES,
            self::PENDING_VETERANCIES,
        ];
    }

    public static function canUse(string $type): bool
    {
        $definition = self::definition($type);
        if (! $definition) {
            return false;
        }

        if ($type === self::MINI_CALENDAR) {
            return EventCalendar::canAccess();
        }

        $component = $definition['component'] ?? null;
        if (! $component) {
            return auth()->check();
        }

        return ! method_exists($component, 'canView') || (bool) $component::canView();
    }

    /** @return array{0:int,1:int} */
    public static function sizeDimensions(string $type, ?string $size): array
    {
        $definition = self::definition($type);
        $fallback = (string) ($definition['default_size'] ?? '1x1');
        $size = is_string($size) && preg_match('/^[1-4]x[1-4]$/', $size) ? $size : $fallback;
        [$width, $height] = array_map('intval', explode('x', $size));

        $min = $definition['min_size'] ?? [1, 1];
        $max = $definition['max_size'] ?? [self::GRID_COLUMNS, self::GRID_ROWS_MAX];

        $width = max((int) $min[0], min((int) $max[0], $width));
        $height = max((int) $min[1], min((int) $max[1], $height));

        return [$width, $height];
    }

    public static function normalizeSize(string $type, ?string $size): string
    {
        [$width, $height] = self::sizeDimensions($type, $size);

        return $width . 'x' . $height;
    }

    /** @return array<string, string> */
    public static function sizeOptions(string $type): array
    {
        $definition = self::definition($type);
        if (! $definition) {
            return [];
        }

        $min = $definition['min_size'] ?? [1, 1];
        $max = $definition['max_size'] ?? [self::GRID_COLUMNS, self::GRID_ROWS_MAX];
        $options = [];

        for ($height = (int) $min[1]; $height <= (int) $max[1]; $height++) {
            for ($width = (int) $min[0]; $width <= (int) $max[0]; $width++) {
                $value = $width . 'x' . $height;
                $options[$value] = $width . '×' . $height;
            }
        }

        return $options;
    }
}
