<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Army extends Model
{
    protected $fillable = [
        'name',
        'faction_id',
    ];

    public function faction()
    {
        return $this->belongsTo(Faction::class);
    }
}
