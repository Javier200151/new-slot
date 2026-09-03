<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityRouletteViewer extends Model
{
    protected $fillable = [
        'room_id',
        'user_id',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    public function room()
    {
        return $this->belongsTo(CommunityRouletteRoom::class, 'room_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
