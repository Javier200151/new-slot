<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Faction extends Model
{
    protected $fillable = [
        'side_id',
        'name',
        'image',
        'description',
    ];

    public function side()
    {
        return $this->belongsTo(Side::class);
    }

    public function armies()
    {
        return $this->hasMany(Army::class);
    }
}
