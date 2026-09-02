<?php

namespace App\Support;

use App\Models\User;

class CommunityArea
{
    public const CANTINA = 'cantina';
    public const FORUM = 'personal';
    // Alias interno para compatibilidad con código/migraciones anteriores.
    public const PERSONAL = self::FORUM;
    public const DIARY = 'diary';
    public const POLLS = 'polls';

    public static function status(User $user): string
    {
        return strtoupper((string) $user->status?->name);
    }

    public static function hasArea(User $user): bool
    {
        return $user->hasRole('admin')
            || self::isForumModerator($user)
            || in_array(self::status($user), ['RECLUTA', 'RESERVA', 'ACTIVO'], true);
    }

    public static function label(User $user): ?string
    {
        if (! self::hasArea($user)) {
            return null;
        }

        return self::status($user) === 'RECLUTA'
            ? 'Reclutamiento'
            : 'Área 51';
    }


    public static function isForumModerator(User $user): bool
    {
        foreach (CommunityForumCategory::personal() as $category) {
            $key = $category['key'];
            if (CommunityForumCategory::can($user, $key, 'moderate')
                || CommunityForumCategory::can($user, $key, 'delete')) {
                return true;
            }
        }

        return CommunityForumCategory::can($user, CommunityForumCategory::CANTINA, 'moderate')
            || CommunityForumCategory::can($user, CommunityForumCategory::CANTINA, 'delete')
            || $user->can('community-forum.moderate')
            || $user->can('community-forum.delete');
    }

    public static function can(User $user, string $section): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if (self::isForumModerator($user) && in_array($section, [self::CANTINA, self::FORUM], true)) {
            return true;
        }

        $status = self::status($user);

        return match ($section) {
            self::DIARY,
            self::CANTINA => in_array($status, ['RECLUTA', 'RESERVA', 'ACTIVO'], true),

            self::FORUM => in_array($status, ['RESERVA', 'ACTIVO'], true),

            self::POLLS => $status === 'ACTIVO',

            default => false,
        };
    }
}
