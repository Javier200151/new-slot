<?php

namespace App\Services;

use App\Models\Role;
use App\Models\SqaGroup;
use App\Models\SqaGroupRoleOwnership;
use App\Models\SqaGroupRoleSource;
use App\Models\SqaGroupUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class SqaGroupLocalRoleSyncService
{
    public function membershipAdded(SqaGroupUser $membership): void
    {
        $membership->loadMissing(['sqaGroup.roles', 'user.roles']);

        if (! $membership->sqaGroup || ! $membership->user) {
            return;
        }

        $this->syncMembershipRoles($membership);
    }

    public function membershipRemoved(SqaGroupUser $membership): void
    {
        $group = SqaGroup::withTrashed()->with('roles')->find($membership->sqa_group_id);
        $user = User::withTrashed()->find($membership->user_id);

        if (! $group || ! $user) {
            return;
        }

        foreach (SqaGroupRoleSource::query()->where('sqa_group_id', $group->id)->where('user_id', $user->id)->pluck('role_id') as $roleId) {
            if ($role = Role::query()->find($roleId)) {
                $this->removeSource($group, $user, $role);
            }
        }
    }

    public function membershipChanged(SqaGroupUser $membership): void
    {
        $oldGroupId = (int) $membership->getOriginal('sqa_group_id');
        $oldUserId = (int) $membership->getOriginal('user_id');

        if ($oldGroupId > 0 && $oldUserId > 0) {
            $oldGroup = SqaGroup::withTrashed()->with('roles')->find($oldGroupId);
            $oldUser = User::withTrashed()->find($oldUserId);

            if ($oldGroup && $oldUser) {
                foreach (SqaGroupRoleSource::query()->where('sqa_group_id', $oldGroup->id)->where('user_id', $oldUser->id)->pluck('role_id') as $roleId) {
                    if ($role = Role::query()->find($roleId)) {
                        $this->removeSource($oldGroup, $oldUser, $role);
                    }
                }
            }
        }

        $this->membershipAdded($membership);
    }

    /**
     * Synchronize all active members after the group's configured roles change.
     *
     * @param array<int, int|string> $oldRoleIds
     */
    public function groupRolesChanged(SqaGroup $group, array $oldRoleIds): void
    {
        $oldIds = collect($oldRoleIds)->map(fn ($id): int => (int) $id)->filter()->unique();
        $newRoles = $group->roles()->get();
        $newIds = $newRoles->pluck('id')->map(fn ($id): int => (int) $id);

        $removedRoles = Role::query()->whereIn('id', $oldIds->diff($newIds)->all())->get();
        $memberships = $group->sqaGroupUsers()->with('user.roles')->get();

        foreach ($memberships as $membership) {
            if (! $membership->user) {
                continue;
            }

            foreach ($removedRoles as $role) {
                $this->removeSource($group, $membership->user, $role);
            }

            $this->syncMembershipRoles($membership);
        }
    }

    public function groupDeleted(SqaGroup $group): void
    {
        $group->loadMissing('roles');

        foreach ($group->sqaGroupUsers()->withTrashed()->with('user.roles')->get() as $membership) {
            if (! $membership->user) {
                continue;
            }

            foreach (SqaGroupRoleSource::query()->where('sqa_group_id', $group->id)->where('user_id', $membership->user->id)->pluck('role_id') as $roleId) {
                if ($role = Role::query()->find($roleId)) {
                    $this->removeSource($group, $membership->user, $role);
                }
            }
        }
    }

    public function groupRestored(SqaGroup $group): void
    {
        $group->loadMissing('roles');

        foreach ($group->sqaGroupUsers()->with('user.roles')->get() as $membership) {
            if (! $membership->user) {
                continue;
            }

            $this->syncMembershipRoles($membership);
        }
    }

    /** @return array{members:int, roles:int} */
    public function syncGroupNow(SqaGroup $group): array
    {
        $group->loadMissing('roles');
        $memberships = $group->sqaGroupUsers()->with('user.roles')->get();

        foreach ($memberships as $membership) {
            if (! $membership->user) {
                continue;
            }

            $this->syncMembershipRoles($membership);
        }

        return [
            'members' => $memberships->count(),
            'roles' => $group->roles->count(),
        ];
    }

    private function syncMembershipRoles(SqaGroupUser $membership): void
    {
        $group = $membership->sqaGroup;
        $user = $membership->user;
        if (! $group || ! $user) {
            return;
        }

        $group->loadMissing('roles');
        $desiredIds = $group->roles->pluck('id')->map(fn ($id): int => (int) $id)->all();
        if ($group->has_coordinator_role && $membership->coordinator && $group->coordinator_role_id) {
            $desiredIds[] = (int) $group->coordinator_role_id;
        }
        $desiredIds = array_unique($desiredIds);
        $actualIds = SqaGroupRoleSource::query()->where('sqa_group_id', $group->id)
            ->where('user_id', $user->id)->pluck('role_id')->map(fn ($id): int => (int) $id)->all();

        foreach (array_diff($actualIds, $desiredIds) as $id) {
            if ($role = Role::find($id)) {
                $this->removeSource($group, $user, $role);
            }
        }
        foreach ($desiredIds as $id) {
            if ($role = Role::find($id)) {
                $this->addSource($group, $user, $role);
            }
        }
    }

    private function addSource(SqaGroup $group, User $user, Role $role): void
    {
        DB::transaction(function () use ($group, $user, $role): void {
            $source = SqaGroupRoleSource::query()->firstOrCreate([
                'sqa_group_id' => $group->id,
                'user_id' => $user->id,
                'role_id' => $role->id,
            ]);

            if (! $source->wasRecentlyCreated) {
                if (! $user->hasRole($role)) {
                    $user->assignRole($role);
                }

                return;
            }

            $ownership = SqaGroupRoleOwnership::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                ],
                [
                    'was_preexisting' => $user->hasRole($role),
                ],
            );

            if (! $user->hasRole($role)) {
                $user->assignRole($role);
            }

            $ownership->touch();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }

    private function removeSource(SqaGroup $group, User $user, Role $role): void
    {
        DB::transaction(function () use ($group, $user, $role): void {
            SqaGroupRoleSource::query()
                ->where('sqa_group_id', $group->id)
                ->where('user_id', $user->id)
                ->where('role_id', $role->id)
                ->delete();

            $hasAnotherSource = SqaGroupRoleSource::query()
                ->where('user_id', $user->id)
                ->where('role_id', $role->id)
                ->exists();

            if ($hasAnotherSource) {
                return;
            }

            $ownership = SqaGroupRoleOwnership::query()
                ->where('user_id', $user->id)
                ->where('role_id', $role->id)
                ->first();

            if ($ownership && ! $ownership->was_preexisting && $user->hasRole($role)) {
                $user->removeRole($role);
            }

            $ownership?->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }
}
