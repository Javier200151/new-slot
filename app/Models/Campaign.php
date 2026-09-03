<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class Campaign extends Model
{
    use Auditable;
    protected $table = 'campaign';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'persistent',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'persistent' => 'boolean',
        ];
    }

    /** Relación canónica. La FK física sigue siendo `campaign_id`. */
    public function activities()
    {
        return $this->hasMany(Activity::class, 'campaign_id');
    }


    public function events()
    {
        return $this->hasManyThrough(
            Event::class,
            Activity::class,
            'campaign_id',
            'activity_id',
        );
    }
}
