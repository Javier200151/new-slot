<?php

namespace App\Policies;

/**
 * @deprecated Utilizar ActivityPolicy.
 *
 * Se conserva temporalmente para que cualquier autorización todavía ligada
 * al modelo Operation continúe funcionando durante la transición.
 */
class OperationPolicy extends ActivityPolicy
{
}
