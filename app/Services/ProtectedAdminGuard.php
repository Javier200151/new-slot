<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

class ProtectedAdminGuard
{
    public function canModify(User $target, ?User $actor = null): bool
    {
        if (! $target->isProtectedAdmin()) {
            return true;
        }

        $actor ??= Auth::user();

        // Procesos internos/CLI sin sesión de usuario pueden seguir ejecutando
        // tareas del sistema. La protección se aplica a acciones administrativas
        // realizadas por una cuenta autenticada distinta del propietario.
        if (! $actor) {
            return true;
        }

        return (int) $actor->getKey() === (int) $target->getKey();
    }

    /**
     * @throws AuthorizationException
     */
    public function authorize(User $target, ?User $actor = null): void
    {
        if ($this->canModify($target, $actor)) {
            return;
        }

        throw new AuthorizationException(
            'La cuenta del administrador principal está protegida y no puede ser modificada por otro usuario.'
        );
    }

    /**
     * @throws AuthorizationException
     */
    public function authorizeUserId(int $userId, ?User $actor = null): void
    {
        $target = User::withTrashed()->find($userId);

        if ($target) {
            $this->authorize($target, $actor);
        }
    }
}
