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
}
