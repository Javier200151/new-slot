<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityDiaryRead extends Model
{
    protected $fillable = [
        'community_diary_id',
        'user_id',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function diary()
    {
        return $this->belongsTo(CommunityDiary::class, 'community_diary_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
