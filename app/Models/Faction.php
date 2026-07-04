<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Faction extends Model
{
    protected $fillable = [
        'name',
        'image',
        'description',
    ];
}
