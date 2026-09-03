<?php

namespace App\Policies;

/**
 * Policy canónica para los días de actividad.
 *
 * Durante esta fase conserva los permisos históricos `activity-days.*`.
 */
class ActivityDayPolicy extends CrudPolicy
{
    protected string $resource = 'activity-days';
}
