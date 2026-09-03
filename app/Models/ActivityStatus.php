<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Estado canónico de actividad.
 *
 * Usa la tabla física canónica `activity_statuses`.
 */
class ActivityStatus extends Model
{
    use Auditable;

    protected $table = 'activity_statuses';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'color',
        'description',
    ];

    public function activities()
    {
        return $this->hasMany(Activity::class, 'activity_status_id');
    }

}
