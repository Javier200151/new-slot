<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class Faction extends Model
{
    use Auditable;
    protected $fillable = [
        'army_id',
        'side_id',
        'name',
    ];

    public function army()
    {
        return $this->belongsTo(Army::class);
    }

    public function side()
    {
        return $this->belongsTo(Side::class);
    }

    /** Relación canónica con actividades enemigas. */
    public function enemyActivities()
    {
        return $this->belongsToMany(
            Activity::class,
            'enemy_faction_operation',
            'faction_id',
            'operation_id'
        );
    }

    /** Alias histórico durante la transición Operation -> Activity. */
    public function enemyOperations()
    {
        return $this->belongsToMany(
            Activity::class,
            'enemy_faction_operation',
            'faction_id',
            'operation_id'
        );
    }
}
