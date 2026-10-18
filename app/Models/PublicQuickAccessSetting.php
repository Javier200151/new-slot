<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicQuickAccessSetting extends Model
{
    protected $fillable = ['items', 'position', 'is_enabled'];

    protected function casts(): array
    {
        return ['items' => 'array', 'is_enabled' => 'boolean'];
    }
}
