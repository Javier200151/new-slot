<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class EventResult extends Model
{
    use Auditable;
    protected $fillable = [
        'name',
    ];

    public function events()
    {
        return $this->hasMany(Event::class);
    }
}
