<?php

namespace App\Models;

/**
 * Alias histórico de compatibilidad.
 *
 * El modelo canónico es ActivityType. Se mantiene esta clase para no romper
 * referencias antiguas ni registros históricos de auditoría durante el cambio.
 */
class OperationType extends ActivityType
{
}
