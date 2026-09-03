<?php

namespace App\Http\Controllers;

/**
 * @deprecated Compatibilidad temporal durante la transición Operation -> Activity.
 *
 * Las rutas públicas ya utilizan PublicActivityController. Mantener este alias
 * evita romper referencias externas o código todavía no migrado en esta fase.
 */
class PublicOperationController extends PublicActivityController
{
}
