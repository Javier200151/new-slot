<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignAar extends Model
{
    protected $table = 'campaign_aars';

    protected $fillable = [
        'campaign_id',
        'event_id',
        'commander_user_id',
        'status',
        'sections',
        'orbat_snapshot',
        'published_at',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'sections' => 'array',
            'orbat_snapshot' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function commander()
    {
        return $this->belongsTo(User::class, 'commander_user_id')->withTrashed();
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
