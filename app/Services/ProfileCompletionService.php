<?php

namespace App\Services;

use App\Models\User;

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
        ];

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

    private function hasValidDiscordId(User $user): bool
    {
        return preg_match('/^\d{17,20}$/', trim((string) $user->discord_id)) === 1;
    }

    private function hasValidSteamId(User $user): bool
    {
        return preg_match('/^\d{17}$/', trim((string) $user->steam_id)) === 1;
    }
}
