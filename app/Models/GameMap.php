<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameMap extends Model
{
    protected $table = 'maps';

    protected $fillable = [
        'name',
        'description',
        'image',
        'url',
        'platform_id',
    ];

    public function platform()
    {
        return $this->belongsTo(Platform::class);
    }
}
