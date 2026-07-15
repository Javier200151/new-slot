<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RadioModel extends Model
{
    protected $fillable = [
        'name',
        'description',
        'channel',
        'block',
        'frequency',
    ];

    protected function casts(): array
    {
        return [
            'channel' => 'boolean',
            'block' => 'boolean',
            'frequency' => 'boolean',
        ];
    }
}
