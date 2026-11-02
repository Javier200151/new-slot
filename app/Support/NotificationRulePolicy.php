<?php

namespace App\Support;

use App\Models\NotificationRule;
use Carbon\CarbonInterface;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

final class NotificationRulePolicy
{
    public static function allows(string $key, ?CarbonInterface $sourceDate = null): bool
    {
        $rule = NotificationRuleCatalog::TYPES[$key] ?? null;
        if (! $rule) return true;

        $default = [
            'enabled' => true,
            'max_age_value' => $rule['default'],
            'max_age_unit' => 'months',
        ];

        // En instalaciones durante el despliegue, no bloquear avisos por falta de tabla.
        $stored = Cache::remember('sqa.notification-rule.' . $key, now()->addSeconds(45), function () use ($key): ?array {
            if (! Schema::hasTable('notification_rules')) return null;
            $row = NotificationRule::query()->find($key);
            return $row ? $row->only(['enabled', 'max_age_value', 'max_age_unit']) : null;
        });

        $settings = array_merge($default, $stored ?? []);
        if (! $settings['enabled']) return false;
        if (! $rule['age'] || $settings['max_age_value'] === null) return true;

        // Si el objeto no contiene una fecha de origen válida, no inventamos una.
        if (! $sourceDate) return true;

        $value = max(1, (int) $settings['max_age_value']);
        $limit = match ($settings['max_age_unit'] ?? 'months') {
            'days' => today()->subDays($value),
            'weeks' => today()->subWeeks($value),
            'years' => today()->subYearsNoOverflow($value),
            default => today()->subMonthsNoOverflow($value),
        };
        return $sourceDate->toDateString() >= $limit->toDateString();
    }

    public static function allowsNotification(Notification $notification): bool
    {
        $key = NotificationRuleCatalog::keyFor($notification);
        return $key === null || self::allows($key, NotificationRuleCatalog::dateFor($notification));
    }
}
