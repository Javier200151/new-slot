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

    public function enemyOperations()
    {
        return $this->belongsToMany(
            Operation::class,
            'enemy_faction_operation',
            'faction_id',
            'operation_id'
        );
    }
}
