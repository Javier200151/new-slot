<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CommunityReaction extends Model
{
    protected $fillable = [
        'user_id',
        'reaction',
    ];

    public static function options(): array
    {
        return [
            'like' => ['emoji' => '👍', 'label' => 'Me gusta'],
            'love' => ['emoji' => '❤️', 'label' => 'Me encanta'],
            'laugh' => ['emoji' => '😂', 'label' => 'Me divierte'],
            'wow' => ['emoji' => '😮', 'label' => 'Me sorprende'],
            'sad' => ['emoji' => '😢', 'label' => 'Me entristece'],
            'angry' => ['emoji' => '😡', 'label' => 'Me enfada'],
            'salute' => ['emoji' => '🫡', 'label' => 'Saludo'],
            'clown' => ['emoji' => '🤡', 'label' => 'Payaso'],
        ];
    }

    public function reactable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
