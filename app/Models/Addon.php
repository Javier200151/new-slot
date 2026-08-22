<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class Addon extends Model
{
    use Auditable;
    protected $fillable = [
        'name',
        'description',
        'mandatory',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'mandatory' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function presets()
    {
        return $this->belongsToMany(
            AddonPreset::class,
            'addon_preset_addon',
            'addon_id',
            'addon_preset_id'
        );
    }
}
