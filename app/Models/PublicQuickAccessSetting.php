<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

use Illuminate\Database\Eloquent\Model;

class PublicQuickAccessSetting extends Model
{
    use Auditable;

    protected $fillable = ['items', 'position', 'is_enabled', 'show_all_to_admins'];

    protected function casts(): array
    {
        return ['items' => 'array', 'is_enabled' => 'boolean', 'show_all_to_admins' => 'boolean'];
    }
}
