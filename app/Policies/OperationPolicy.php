<?php

namespace App\Policies;

use App\Models\Operation;
use App\Models\User;
use App\Support\OperationTypeAccess;

class OperationPolicy
{
    public function viewAny(User $user): bool
    {
        return OperationTypeAccess::canAny(
            $user,
            'operations',
            'view',
        );
    }

    public function view(User $user, Operation $operation): bool
    {
        return OperationTypeAccess::can(
            $user,
            'operations',
            'view',
            $operation->operation_type_id,
        );
    }

    public function create(User $user): bool
    {
        return OperationTypeAccess::canAny(
            $user,
            'operations',
            'create',
        );
    }

    public function update(User $user, Operation $operation): bool
    {
        return OperationTypeAccess::can(
            $user,
            'operations',
            'update',
            $operation->operation_type_id,
        );
    }

    public function delete(User $user, Operation $operation): bool
    {
        return OperationTypeAccess::can(
            $user,
            'operations',
            'delete',
            $operation->operation_type_id,
        );
    }

    public function deleteAny(User $user): bool
    {
        // Evitamos borrados masivos mezclando tipos con permisos distintos.
        return $user->hasRole('admin');
    }
}
