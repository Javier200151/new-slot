<?php

namespace App\Services;

use App\Models\User;
use App\Services\Wiki\WikiAccessPolicy;
use App\Services\Wiki\WikiLinkClient;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ProfileCompletionService
{
    /**
     * @return array{
     *     total:int,
     *     completed:int,
     *     missing_count:int,
     *     percent:int,
     *     is_complete:bool,
     *     steps:array<int, array{key:string,label:string,description:string,anchor:string,complete:bool}>,
     *     missing:array<int, array{key:string,label:string,description:string,anchor:string,complete:bool}>
     * }
     */
    public function forUser(User $user): array
    {
        $steps = [
            [
                'key' => 'discord_id',
                'label' => 'Discord ID',
                'description' => 'ID numérico de Discord para automatizar roles y apodos.',
                'anchor' => 'profile-discord-id',
                'complete' => $this->hasValidDiscordId($user),
            ],
            [
                'key' => 'steam_id',
                'label' => 'Steam ID64',
                'description' => 'Steam ID64 para ArmaSquads y los procedimientos de miembro.',
                'anchor' => 'profile-steam-id',
                'complete' => $this->hasValidSteamId($user),
            ],
            [
                'key' => 'birthday_visibility',
                'label' => 'Privacidad del cumpleaños',
                'description' => 'Confirma si quieres que tu cumpleaños sea público o privado.',
                'anchor' => 'profile-birthday-visibility',
                'complete' => $this->hasConfirmedBirthdayVisibility($user),
            ],
        ];

        // Only historical Wiki accounts awaiting linking are pending.
        // New users without a Wiki account should access via OIDC normally.
        if ($this->hasPendingLegacyWikiAccount($user)) {
            $steps[] = [
                'key' => 'wiki_link',
                'label' => 'Vincular cuenta anterior de la Wiki',
                'description' => 'Vincula tu cuenta antigua de la Wiki para conservar libros, comentarios y permisos.',
                'anchor' => 'profile-linked-accounts',
                'complete' => false,
            ];
        }

        $completed = count(array_filter(
            $steps,
            static fn (array $step): bool => $step['complete'],
        ));

        $total = count($steps);
        $missing = array_values(array_filter(
            $steps,
            static fn (array $step): bool => ! $step['complete'],
        ));

        return [
            'total' => $total,
            'completed' => $completed,
            'missing_count' => count($missing),
            'percent' => $total > 0 ? (int) round(($completed / $total) * 100) : 100,
            'is_complete' => count($missing) === 0,
            'steps' => $steps,
            'missing' => $missing,
        ];
    }


    private function hasPendingLegacyWikiAccount(User $user): bool
    {
        if (! $user->exists || ! $user->getKey() || ! config('wiki_link.enabled', false)) {
            return false;
        }

        try {
            $policy = app(WikiAccessPolicy::class);
            if (! $policy->canAuthenticate($user) || $policy->isSuperAdmin($user)) {
                return false;
            }

            return Cache::remember('wiki_link:pending_profile:'.$user->getKey(), now()->addMinutes(2),
                function () use ($user): bool {
                    $response = app(WikiLinkClient::class)->call(
                        'status', 'sqa-user-'.$user->getKey(), (string) $user->email
                    );

                    return ($response['status'] ?? null) === 'pending_link';
                });
        } catch (Throwable $exception) {
            // An unavailable Wiki must not make the home page fail or falsely flag new recruits.
            report($exception);
            return false;
        }
    }

    private function hasConfirmedBirthdayVisibility(User $user): bool
    {
        return filled($user->getAttributes()['birthday_visibility_confirmed_at'] ?? null);
    }

    private function hasValidDiscordId(User $user): bool
    {
        return preg_match('/^\d{17,20}$/', trim((string) $user->discord_id)) === 1;
    }

    private function hasValidSteamId(User $user): bool
    {
        return preg_match('/^\d{17}$/', trim((string) $user->steam_id)) === 1;
    }
}
