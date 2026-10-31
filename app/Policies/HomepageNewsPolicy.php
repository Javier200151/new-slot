<?php

namespace App\Policies;

class HomepageNewsPolicy extends CrudPolicy
{
    protected string $resource = 'homepage-news';

    public function restore(\App\Models\User $user, \App\Models\HomepageNews $record): bool
    {
        return $user->can('homepage-news.delete');
    }

    public function restoreAny(\App\Models\User $user): bool
    {
        return $user->can('homepage-news.delete');
    }
}
