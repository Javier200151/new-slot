<?php

namespace App\Models;

/**
 * @deprecated Utilizar Activity en código nuevo.
 *
 * Capa temporal de compatibilidad mientras migramos el código de
 * Operation -> Activity. Mantener esta clase evita romper referencias
 * existentes antes del cambio definitivo de nomenclatura.
 */
class Operation extends Activity
{
}
