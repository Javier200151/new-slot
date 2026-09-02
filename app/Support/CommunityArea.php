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
            || CommunityForumCategory::hasVisibleForumCategory($user)
            || $user->can('community-forum.moderate')
            || $user->can('community-forum.delete');
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
        foreach (CommunityForumCategory::landing() as $key => $category) {
            if (($category['permission_resource'] ?? null) === null) {
                continue;
            }

            if (CommunityForumCategory::can($user, $key, 'moderate')
                || CommunityForumCategory::can($user, $key, 'delete')) {
                return true;
            }
        }

        return $user->can('community-forum.moderate')
            || $user->can('community-forum.delete');
    }

    public static function can(User $user, string $section): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return match ($section) {
            self::DIARY => CommunityForumCategory::canView(
                $user,
                CommunityForumCategory::DIARY,
            ),

            self::CANTINA => CommunityForumCategory::canView(
                $user,
                CommunityForumCategory::CANTINA,
            ),

            self::FORUM => CommunityForumCategory::hasVisiblePersonalCategory($user),

            self::POLLS => self::status($user) === 'ACTIVO',

            default => false,
        };
    }
}
