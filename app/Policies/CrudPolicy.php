<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

abstract class CrudPolicy
{
    protected string $resource;

    public function viewAny(User $user): bool
    {
        return $user->can("{$this->resource}.view");
    }

    public function view(User $user, Model $record): bool
    {
        return $user->can("{$this->resource}.view");
    }

    public function create(User $user): bool
    {
        return $user->can("{$this->resource}.create");
    }

    public function update(User $user, Model $record): bool
    {
        return $user->can("{$this->resource}.update");
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->can("{$this->resource}.delete");
    }

    public function deleteAny(User $user): bool
    {
        return $user->can("{$this->resource}.delete");
    }
}