<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Día canónico asociado a actividades.
 *
 * Usa la tabla física canónica `activity_days` y el pivote `activity_day_assignments`.
 */
class ActivityDay extends Model
{
    use Auditable;

    protected $table = 'activity_days';

    public $timestamps = false;

    protected $fillable = [
        'name',
    ];

    public function activities()
    {
        return $this->belongsToMany(
            Activity::class,
            'activity_day_assignments',
            'activity_day_id',
            'activity_id'
        );
    }

}
