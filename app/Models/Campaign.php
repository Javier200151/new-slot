<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    protected $table = 'campaign';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'persistent',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'persistent' => 'boolean',
        ];
    }
}
