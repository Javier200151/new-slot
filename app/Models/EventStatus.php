<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class EventStatus extends Model
{
    use Auditable;
    protected $table = 'event_status';

    protected $fillable = [
        'name',
    ];

    public function events()
    {
        return $this->hasMany(Event::class, 'event_status_id');
    }
}
