<?php

namespace App\Services;

use App\Models\PersonalDashboard;
use App\Models\PersonalDashboardWidget;
use App\Models\User;
use App\Support\PersonalDashboardWidgetRegistry;
use Illuminate\Support\Facades\DB;

class PersonalDashboardService
{
    public function activeFor(User $user): PersonalDashboard
    {
        return DB::transaction(function () use ($user): PersonalDashboard {
            $dashboard = PersonalDashboard::query()
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $dashboard) {
                $dashboard = PersonalDashboard::query()
                    ->where('user_id', $user->id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();
            }

            if (! $dashboard) {
                $dashboard = PersonalDashboard::query()->create([
                    'user_id' => $user->id,
                    'name' => 'Principal',
                    'is_active' => true,
                ]);

                $this->seedDefaultWidgets($dashboard);
            } elseif (! $dashboard->is_active) {
                PersonalDashboard::query()
                    ->where('user_id', $user->id)
                    ->update(['is_active' => false]);

                $dashboard->forceFill(['is_active' => true])->save();
            }

            return $dashboard->fresh('widgets');
        });
    }

    public function activate(User $user, int $dashboardId): PersonalDashboard
    {
        return DB::transaction(function () use ($user, $dashboardId): PersonalDashboard {
            $dashboard = PersonalDashboard::query()
                ->where('user_id', $user->id)
                ->whereKey($dashboardId)
                ->lockForUpdate()
                ->firstOrFail();

            PersonalDashboard::query()
                ->where('user_id', $user->id)
                ->where('id', '!=', $dashboard->id)
                ->update(['is_active' => false]);

            if (! $dashboard->is_active) {
                $dashboard->forceFill(['is_active' => true])->save();
            }

            return $dashboard->fresh('widgets');
        });
    }

    public function create(User $user, string $name, bool $seedDefaults = false): PersonalDashboard
    {
        return DB::transaction(function () use ($user, $name, $seedDefaults): PersonalDashboard {
            PersonalDashboard::query()
                ->where('user_id', $user->id)
                ->update(['is_active' => false]);

            $dashboard = PersonalDashboard::query()->create([
                'user_id' => $user->id,
                'name' => $name,
                'is_active' => true,
            ]);

            if ($seedDefaults) {
                $this->seedDefaultWidgets($dashboard);
            }

            return $dashboard->fresh('widgets');
        });
    }

    public function duplicate(User $user, PersonalDashboard $source, string $name): PersonalDashboard
    {
        abort_unless((int) $source->user_id === (int) $user->id, 403);

        return DB::transaction(function () use ($user, $source, $name): PersonalDashboard {
            PersonalDashboard::query()
                ->where('user_id', $user->id)
                ->update(['is_active' => false]);

            $copy = PersonalDashboard::query()->create([
                'user_id' => $user->id,
                'name' => $name,
                'is_active' => true,
            ]);

            $source->loadMissing('widgets');
            foreach ($source->widgets as $widget) {
                $copy->widgets()->create([
                    'type' => $widget->type,
                    'position' => $widget->position,
                    'size' => $widget->size,
                    'settings' => $widget->settings,
                ]);
            }

            return $copy->fresh('widgets');
        });
    }

    public function seedDefaultWidgets(PersonalDashboard $dashboard): void
    {
        $position = 0;

        foreach (PersonalDashboardWidgetRegistry::defaultTypes() as $type) {
            $definition = PersonalDashboardWidgetRegistry::definition($type);
            if (! $definition) {
                continue;
            }

            $dashboard->widgets()->firstOrCreate(
                ['type' => $type],
                [
                    'position' => $position++,
                    'size' => $definition['default_size'],
                    'settings' => [],
                ],
            );
        }
    }
}
