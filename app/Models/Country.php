<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $fillable = [
        'name',
        'image',
    ];

    public function armies()
    {
        return $this->hasMany(Army::class);
    }
}
