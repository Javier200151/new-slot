<?php

namespace App\Policies;

use App\Models\ForumCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ForumCategoryPolicy extends CrudPolicy
{
    protected string $resource = 'community-forum-categories';

    public function delete(User $user, Model $record): bool
    {
        return parent::delete($user, $record)
            && $record instanceof ForumCategory
            && ! $record->is_system
            && ! $record->posts()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
