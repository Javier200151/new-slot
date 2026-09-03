<?php

namespace App\Console\Commands;

/**
 * Alias de compatibilidad para automatizaciones o documentación antigua.
 * El comando canónico es activities:import-2026.
 */
class ImportOperations2026 extends ImportActivities2026
{
    protected $signature = 'operations:import-2026
        {file=storage/app/imports/misiones_2026_final.json : JSON de actividades}
        {--dry-run : Valida y muestra el resumen sin escribir en la base de datos}';

    protected $description =
        'Alias histórico de activities:import-2026';
}
