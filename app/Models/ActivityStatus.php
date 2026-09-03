<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Estado canónico de actividad.
 *
 * Durante la transición sigue usando la tabla histórica `operation_status`.
 */
class ActivityStatus extends Model
{
    use Auditable;

    protected $table = 'operation_status';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'color',
        'description',
    ];

    public function activities()
    {
        return $this->hasMany(Activity::class, 'operation_status_id');
    }

    /** Alias histórico durante la transición. */
    public function operations()
    {
        return $this->activities();
    }
}
