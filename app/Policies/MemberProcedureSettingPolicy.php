<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class MemberProcedureSettingPolicy extends CrudPolicy
{
    protected string $resource = 'member-procedure-settings';

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, Model $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
