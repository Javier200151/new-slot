<?php

namespace App\Policies;

/**
 * Policy canónica para los estados de actividad.
 *
 * Durante esta fase conserva los permisos históricos `activity-statuses.*`.
 */
class ActivityStatusPolicy extends CrudPolicy
{
    protected string $resource = 'activity-statuses';
}
