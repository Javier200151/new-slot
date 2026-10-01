<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\PermissionCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tipo canónico de actividad.
 *
 * Usa la tabla física canónica `activity_types`.
 */
class ActivityType extends Model
{
    use Auditable;

    protected $table = 'activity_types';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'description',
        'oficial',
        'color',
        'uses_enemy_factions',
        'uses_event_result',
        'supports_ocap',
        'supports_respawn',
        'supports_jip',
        'awards_metopa',
        'uses_campaign',
        'uses_days',
        'uses_image',
        'uses_map',
        'uses_period',
        'uses_editor',
        'uses_day_or_night',
        'uses_pbo',
        'uses_briefing',
        'uses_orbat',
        'uses_radio',
        'uses_addons',
        'uses_multiclans',
        'uses_reservations',
        'uses_event_briefing',
        'uses_event_end_date',
    ];

    protected static function booted(): void
    {
        static::created(function (ActivityType $activityType): void {
            if (
                ! Schema::hasTable('permissions')
                || ! Schema::hasTable('roles')
                || ! Schema::hasTable('role_has_permissions')
            ) {
                return;
            }

            $guard = PermissionCatalog::guard();
            $permissionNames = [];

            foreach (PermissionCatalog::resources() as $resource => $definition) {
                if (! PermissionCatalog::isActivityTypeScoped($resource)) {
                    continue;
                }

                foreach (PermissionCatalog::actionsFor($resource) as $action) {
                    $permissionName = PermissionCatalog::activityTypePermissionName(
                        $resource,
                        (int) $activityType->id,
                        $action,
                    );

                    Permission::firstOrCreate([
                        'name' => $permissionName,
                        'guard_name' => $guard,
                    ]);

                    $permissionNames[] = $permissionName;
                }
            }

            $adminRole = Role::query()
                ->where('name', 'admin')
                ->where('guard_name', $guard)
                ->first();

            if ($adminRole && $permissionNames !== []) {
                $adminRole->givePermissionTo($permissionNames);
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }

    public function activities()
    {
        return $this->hasMany(Activity::class, 'activity_type_id');
    }


    public function usesEnemyFactions(): bool
    {
        return (bool) $this->uses_enemy_factions;
    }

    public function usesEventResult(): bool
    {
        return (bool) $this->uses_event_result;
    }

    public function supportsOcap(): bool
    {
        return (bool) $this->supports_ocap;
    }

    public function supportsRespawn(): bool
    {
        return (bool) $this->supports_respawn;
    }

    public function supportsJip(): bool
    {
        return (bool) $this->supports_jip;
    }

    public function awardsMetopa(): bool
    {
        return (bool) $this->awards_metopa;
    }


    public function usesCampaign(): bool { return $this->getAttribute('uses_campaign') === null ? true : (bool) $this->uses_campaign; }
    public function usesDays(): bool { return $this->getAttribute('uses_days') === null ? true : (bool) $this->uses_days; }
    public function usesImage(): bool { return $this->getAttribute('uses_image') === null ? true : (bool) $this->uses_image; }
    public function usesMap(): bool { return $this->getAttribute('uses_map') === null ? true : (bool) $this->uses_map; }
    public function usesPeriod(): bool { return $this->getAttribute('uses_period') === null ? true : (bool) $this->uses_period; }
    public function usesEditor(): bool { return $this->getAttribute('uses_editor') === null ? true : (bool) $this->uses_editor; }
    public function usesDayOrNight(): bool { return $this->getAttribute('uses_day_or_night') === null ? true : (bool) $this->uses_day_or_night; }
    public function usesPbo(): bool { return $this->getAttribute('uses_pbo') === null ? true : (bool) $this->uses_pbo; }
    public function usesBriefing(): bool { return $this->getAttribute('uses_briefing') === null ? true : (bool) $this->uses_briefing; }
    public function usesOrbat(): bool { return $this->getAttribute('uses_orbat') === null ? true : (bool) $this->uses_orbat; }
    public function usesRadio(): bool { return $this->getAttribute('uses_radio') === null ? true : (bool) $this->uses_radio; }
    public function usesAddons(): bool { return $this->getAttribute('uses_addons') === null ? true : (bool) $this->uses_addons; }
    public function usesMulticlans(): bool { return $this->getAttribute('uses_multiclans') === null ? true : (bool) $this->uses_multiclans; }
    public function usesReservations(): bool { return $this->getAttribute('uses_reservations') === null ? true : (bool) $this->uses_reservations; }
    public function usesEventBriefing(): bool { return $this->getAttribute('uses_event_briefing') === null ? true : (bool) $this->uses_event_briefing; }
    public function usesEventEndDate(): bool { return $this->getAttribute('uses_event_end_date') === null ? true : (bool) $this->uses_event_end_date; }

    protected function casts(): array
    {
        return [
            'oficial' => 'boolean',
            'uses_enemy_factions' => 'boolean',
            'uses_event_result' => 'boolean',
            'supports_ocap' => 'boolean',
            'supports_respawn' => 'boolean',
            'supports_jip' => 'boolean',
            'awards_metopa' => 'boolean',
            'uses_campaign' => 'boolean',
            'uses_days' => 'boolean',
            'uses_image' => 'boolean',
            'uses_map' => 'boolean',
            'uses_period' => 'boolean',
            'uses_editor' => 'boolean',
            'uses_day_or_night' => 'boolean',
            'uses_pbo' => 'boolean',
            'uses_briefing' => 'boolean',
            'uses_orbat' => 'boolean',
            'uses_radio' => 'boolean',
            'uses_addons' => 'boolean',
            'uses_multiclans' => 'boolean',
            'uses_reservations' => 'boolean',
            'uses_event_briefing' => 'boolean',
            'uses_event_end_date' => 'boolean',
        ];
    }
}
