<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Addon extends Model
{
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
