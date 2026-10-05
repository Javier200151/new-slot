<?php

namespace App\Policies;

use App\Models\User;
use App\Services\ProtectedAdminGuard;
use Illuminate\Database\Eloquent\Model;

class UserPolicy extends CrudPolicy
{
    protected string $resource = 'users';

    public function update(User $user, Model $record): bool
    {
        return $user->can('users.update')
            && (! $record instanceof User
                || app(ProtectedAdminGuard::class)->canModify($record, $user));
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->can('users.delete')
            && (! $record instanceof User
                || app(ProtectedAdminGuard::class)->canModify($record, $user));
    }
}
