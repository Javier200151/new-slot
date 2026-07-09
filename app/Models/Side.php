<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Side extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'name',
        'description',
        'image',
        
    ];
}
