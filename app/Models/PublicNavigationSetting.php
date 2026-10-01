<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicNavigationSetting extends Model
{
    protected $fillable = [
        'items',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
        ];
    }
}
