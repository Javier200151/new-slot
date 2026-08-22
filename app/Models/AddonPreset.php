<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class AddonPreset extends Model
{
    use Auditable;
    protected $fillable = [
        'name',
        'description',
    ];

    public function addons()
    {
        return $this->belongsToMany(
            Addon::class,
            'addon_preset_addon',
            'addon_preset_id',
            'addon_id'
        );
    }
}
