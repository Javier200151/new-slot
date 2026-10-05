<?php

namespace App\Policies;

use App\Models\EventComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class EventCommentPolicy extends CrudPolicy
{
    protected string $resource = 'event-comments';

    public function update(User $user, Model $record): bool
    {
        return ($record instanceof EventComment && (int) $record->user_id === (int) $user->id)
            || $user->can('event-comments.update');
    }

    public function delete(User $user, Model $record): bool
    {
        return ($record instanceof EventComment && (int) $record->user_id === (int) $user->id)
            || $user->can('event-comments.delete');
    }
}
