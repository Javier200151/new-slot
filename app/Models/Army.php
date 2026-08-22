<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class Army extends Model
{
    use Auditable;
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
