<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Army extends Model
{
    protected $fillable = [
        'country_id',
        'name',
        'description',
        'image',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function factions()
    {
        return $this->hasMany(Faction::class);
    }
}
