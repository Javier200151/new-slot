<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\Auditable;
use App\Services\SqaGroupDiscordSyncService;
use App\Services\SqaGroupLocalRoleSyncService;

class SqaGroup extends Model
{
    use SoftDeletes, Auditable;

    protected $fillable = [
        'name',
        'large_name',
        'description',
        'image',
        'icon',
        'color',
        'display_order',
        'show_in_organization',
        'has_coordinator_role',
        'coordinator_role_id',
        'discord_role_id',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'show_in_organization' => 'boolean',
            'has_coordinator_role' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updated(function (self $group): void {
            if ($group->wasChanged('discord_role_id')) {
                app(SqaGroupDiscordSyncService::class)->groupRoleChanged(
                    $group,
                    $group->getOriginal('discord_role_id'),
                );
            }
        });

        static::deleted(function (self $group): void {
            app(SqaGroupDiscordSyncService::class)->groupDeleted($group);
            app(SqaGroupLocalRoleSyncService::class)->groupDeleted($group);
        });

        static::restored(function (self $group): void {
            app(SqaGroupDiscordSyncService::class)->groupRestored($group);
            app(SqaGroupLocalRoleSyncService::class)->groupRestored($group);
        });

        static::saved(function (self $group): void {
            if ($group->has_coordinator_role || ! $group->wasChanged('has_coordinator_role')) {
                return;
            }

            $values = ['coordinator' => false];

            if ($userId = auth()->id()) {
                $values['updated_by'] = $userId;
            }

            $group->sqaGroupUsers()
                ->where('coordinator', true)
                ->update($values);
        });
    }

    public function sqaGroupUsers()
    {
        return $this->hasMany(SqaGroupUser::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'sqa_group_users')
            ->withPivot([
                'main',
                'coordinator',
                'updated_by',
                'deleted_at',
            ])
            ->withTimestamps()
            ->wherePivotNull('deleted_at');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'sqa_group_roles')
            ->withTimestamps();
    }

    public function coordinatorWebRole()
    {
        return $this->belongsTo(Role::class, 'coordinator_role_id');
    }

    public function coordinatorAssignment()
    {
        return $this->hasOne(SqaGroupUser::class)
            ->where('coordinator', true);
    }
}
