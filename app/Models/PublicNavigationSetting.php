<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

use Illuminate\Database\Eloquent\Model;

class PublicNavigationSetting extends Model
{
    use Auditable;

    protected $fillable = [
        'items',
        'show_all_to_admins',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'show_all_to_admins' => 'boolean',
        ];
    }
}
