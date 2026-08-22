<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\Auditable;

class Streamer extends Model
{
    use SoftDeletes, Auditable;

    protected $fillable = [
        'user_id',
        'enable',
        'twich_channel',
        'youtube_channel',
        'other_channel',
    ];

    protected function casts(): array
    {
        return [
            'enable' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function streams()
    {
        return $this->hasMany(Stream::class);
    }
}
