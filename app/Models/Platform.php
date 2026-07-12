<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Platform extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name',
        'image',
    ];
}
