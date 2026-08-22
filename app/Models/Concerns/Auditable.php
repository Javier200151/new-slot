<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

trait Auditable
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()

            /*
             * Logs normales de cambios de BD.
             */
            ->useLogName('audit')

            /*
             * Guardar todos los campos.
             */
            ->logAll()

            /*
             * JAMÁS guardar estas credenciales.
             */
            ->logExcept([
                'password',
                'remember_token',
            ])

            /*
             * No generar log solo porque Laravel
             * haya cambiado updated_at.
             */
            ->dontLogIfAttributesChangedOnly([
                'updated_at',
            ])

            /*
             * En updates guardar únicamente
             * los campos modificados.
             */
            ->logOnlyDirty()

            /*
             * No guardar actividades vacías.
             */
            ->dontLogEmptyChanges();
    }
}