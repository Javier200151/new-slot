<?php

namespace App\Policies;

/**
 * Policy canónica para los tipos de actividad.
 *
 * Durante esta fase conserva los permisos históricos `activity-types.*`
 * para no alterar IDs ni asignaciones en Spatie Permission.
 */
class ActivityTypePolicy extends CrudPolicy
{
    protected string $resource = 'activity-types';
}
