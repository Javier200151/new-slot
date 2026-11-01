<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

use Illuminate\Database\Eloquent\Model;

class PublicQuickAccessSetting extends Model
{
    use Auditable;

    protected $fillable = ['items', 'position', 'is_enabled'];

    protected function casts(): array
    {
        return ['items' => 'array', 'is_enabled' => 'boolean'];
    }
}
