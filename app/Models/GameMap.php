<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class GameMap extends Model
{
    use Auditable;
    protected $table = 'maps';

    protected $fillable = [
        'name',
        'description',
        'image',
        'url',
        'platform_id',
    ];

    public function platform()
    {
        return $this->belongsTo(Platform::class);
    }

    /** Relación canónica con actividades. */
    public function activities()
    {
        return $this->hasMany(Activity::class, 'map_id');
    }

    /** Alias histórico durante la transición Operation -> Activity. */
    public function operations()
    {
        return $this->hasMany(Activity::class, 'map_id');
    }
}
