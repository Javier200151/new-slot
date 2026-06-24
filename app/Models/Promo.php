<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promo extends Model
{
    protected $table = 'promo';

    protected $fillable = [
        'id',
        'image',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'promo_id');
    }
}