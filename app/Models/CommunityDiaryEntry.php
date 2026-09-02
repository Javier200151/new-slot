<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class CommunityDiaryEntry extends Model
{
    use Auditable;

    protected $fillable = [
        'community_diary_id',
        'user_id',
        'event_id',
        'content',
        'squad_group',
        'squad_roster',
    ];

    protected $touches = ['diary'];

    protected function casts(): array
    {
        return [
            'squad_roster' => 'array',
        ];
    }

    public function diary()
    {
        return $this->belongsTo(CommunityDiary::class, 'community_diary_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function event()
    {
        return $this->belongsTo(Event::class)->withTrashed();
    }

    public function comments()
    {
        return $this->hasMany(
            CommunityDiaryComment::class,
            'community_diary_entry_id',
        )->oldest('created_at')->oldest('id');
    }
}
