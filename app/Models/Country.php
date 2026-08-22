<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class Country extends Model
{
    use Auditable;
    protected $fillable = [
        'name',
        'image',
    ];

    public function armies()
    {
        return $this->hasMany(Army::class);
    }
}
