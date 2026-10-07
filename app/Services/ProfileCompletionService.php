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
        $discordComplete = $this->hasValidDiscordId($user);
        $steamComplete = $this->hasValidSteamId($user);

        $steps = [
            [
                'key' => 'discord_id',
                'label' => 'Discord',
                'description' => $this->discordDescription($user, $discordComplete),
                'anchor' => 'profile-linked-accounts',
                'complete' => $discordComplete,
            ],
            [
                'key' => 'steam_id',
                'label' => 'Steam',
                'description' => $this->steamDescription($user, $steamComplete),
                'anchor' => 'profile-linked-accounts',
                'complete' => $steamComplete,
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

    private function discordDescription(User $user, bool $complete): string
    {
        if (! $complete) {
            return 'Enlaza tu cuenta de Discord.';
        }

        if ($this->hasRawLinkedAt($user, 'discord_linked_at')) {
            return filled($user->discord_username)
                ? 'Vinculado como @' . $user->discord_username
                : 'Cuenta de Discord vinculada.';
        }

        return 'Configurado manualmente; puedes verificarlo con Discord.';
    }

    private function steamDescription(User $user, bool $complete): string
    {
        if (! $complete) {
            return 'Enlaza tu cuenta de Steam.';
        }

        if ($this->hasRawLinkedAt($user, 'steam_linked_at')) {
            return 'Steam vinculado · ' . $user->steam_id;
        }

        return 'SteamID64 configurado manualmente.';
    }

    private function hasRawLinkedAt(User $user, string $attribute): bool
    {
        $attributes = $user->getAttributes();

        return array_key_exists($attribute, $attributes) && $attributes[$attribute] !== null;
    }
}
