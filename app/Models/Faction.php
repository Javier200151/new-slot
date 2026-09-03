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
            'activity_enemy_faction',
            'faction_id',
            'activity_id'
        );
    }

}
