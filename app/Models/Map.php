<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Map extends Model
{
    protected $table = 'maps';

    protected $fillable = [
        'name',
        'description',
        'image',
        'url',
        'platform_id',
    ];

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }
}