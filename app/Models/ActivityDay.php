<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Día canónico asociado a actividades.
 *
 * Durante la transición mantiene la tabla/pivote históricos.
 */
class ActivityDay extends Model
{
    use Auditable;

    protected $table = 'operation_day';

    public $timestamps = false;

    protected $fillable = [
        'name',
    ];

    public function activities()
    {
        return $this->belongsToMany(
            Activity::class,
            'operation_operation_day',
            'operation_day_id',
            'operation_id'
        );
    }

    /** Alias histórico durante la transición. */
    public function operations()
    {
        return $this->activities();
    }
}
