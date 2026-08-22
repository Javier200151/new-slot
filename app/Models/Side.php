<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class Side extends Model
{
    use Auditable;
    public $timestamps = false;

    protected $fillable = [
        'name',
        'description',
        'image',
    ];

    public function factions()
    {
        return $this->hasMany(Faction::class);
    }
}
