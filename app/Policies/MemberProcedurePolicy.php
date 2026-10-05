<?php

namespace App\Policies;

use App\Models\MemberProcedure;
use App\Models\User;

class MemberProcedurePolicy extends CrudPolicy
{
    protected string $resource = 'member-procedures';

    public function delete(User $user, \Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
