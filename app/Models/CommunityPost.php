<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommunityPost extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'channel',
        'user_id',
        'title',
        'body',
        'is_pinned',
        'is_locked',
        'locked_at',
        'locked_by',
        'community_process_id',
        'forum_category_id',
    ];


    protected static function booted(): void
    {
        static::deleting(function (CommunityPost $post): void {
            if ($post->isForceDeleting()) {
                return;
            }

            $post->poll?->delete();
            $post->process?->delete();
        });
    }

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'is_locked' => 'boolean',
            'locked_at' => 'datetime',
        ];
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function comments()
    {
        return $this->hasMany(CommunityPostComment::class)->oldest();
    }

    public function forumCategory()
    {
        return $this->belongsTo(ForumCategory::class, 'forum_category_id');
    }

    public function process()
    {
        return $this->belongsTo(CommunityProcess::class, 'community_process_id');
    }

    public function poll()
    {
        return $this->hasOne(CommunityPoll::class, 'community_post_id');
    }

    public function lockedBy()
    {
        return $this->belongsTo(User::class, 'locked_by')->withTrashed();
    }

    public function subscriptions()
    {
        return $this->morphMany(CommunitySubscription::class, 'subscribable');
    }

    public function reactions(): MorphMany
    {
        return $this->morphMany(CommunityReaction::class, 'reactable');
    }
}
