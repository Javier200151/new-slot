<?php

namespace App\Services\Wiki;

use App\Models\User;

/** Central policy for BookStack SSO. No roles from Spatie are consulted. */
class WikiAccessPolicy
{
    private const ALLOWED_STATUSES = ['RECLUTA', 'ACTIVO', 'RESERVA'];

    public function isSuperAdmin(User $user): bool
    {
        return ! $user->trashed()
            && $user->hasVerifiedEmail()
            && $user->isProtectedAdmin();
    }

    public function canAuthenticate(User $user): bool
    {
        if ($user->trashed() || ! $user->hasVerifiedEmail()) {
            return false;
        }

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return in_array(mb_strtoupper(trim((string) $user->status?->name)), self::ALLOWED_STATUSES, true);
    }

    /**
     * Groups that the future OIDC provider may emit. Non-authorized identities
     * yield no roles. Uses SQA memberships, never web RBAC/Spatie roles.
     * These are proposed claim values; they do not yet synchronize BookStack.
     *
     * @return list<string>
     */
    public function oidcGroups(User $user): array
    {
        if (! $this->canAuthenticate($user)) {
            return [];
        }

        $groups = ['Miembro'];
        if ($this->isSuperAdmin($user)) {
            $groups[] = 'sqa-bookstack-superadmin';
        }

        $memberships = $user->sqaGroups()->get(['sqa_groups.id', 'sqa_groups.name', 'sqa_groups.has_coordinator_role']);
        foreach ($memberships as $group) {
            $name = mb_strtoupper(trim((string) $group->name));
            if ($name === '' || $name === 'SQA') {
                continue; // SQA is umbrella group, not an automatic private role.
            }
            $groups[] = $name;
            if ((bool) $group->has_coordinator_role && (bool) $group->pivot?->coordinator) {
                $groups[] = 'Coordinador de '.$name;
            }
        }
        return array_values(array_unique($groups));
    }

    public function sqaGroupNames(User $user): array
    {
        return $user->sqaGroups()->pluck('name')->map(fn ($name) => (string) $name)->all();
    }
}
