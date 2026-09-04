<?php

namespace App\Policies;

use App\Models\CampaignAar;
use App\Models\User;
use App\Services\CampaignAarService;

class CampaignAarPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, CampaignAar $aar): bool
    {
        return true;
    }

    public function update(User $user, CampaignAar $aar): bool
    {
        if ($user->hasPermissionTo('campaign-aars.manage')) {
            return true;
        }

        $aar->loadMissing([
            'campaign',
            'event.activity',
        ]);

        if (
            (int) $aar->commander_user_id === (int) $user->id
            || (int) $aar->campaign?->editor_id === (int) $user->id
            || (int) $aar->event?->activity?->editor_id === (int) $user->id
        ) {
            return true;
        }

        /*
         * Defensa adicional para AAR ya creados con commander_user_id vacío
         * por versiones anteriores: comprobamos en tiempo real quién ocupa el
         * tipo de slot Mando Global del ORBAT.
         */
        $eventCommander = $aar->event
            ? app(CampaignAarService::class)->commanderForEvent($aar->event)
            : null;

        return (int) ($eventCommander?->id ?? 0) === (int) $user->id;
    }

    public function delete(User $user, CampaignAar $aar): bool
    {
        return false;
    }
}
