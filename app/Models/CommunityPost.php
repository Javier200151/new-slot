<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CommunityPost extends Model
{
    use Auditable, SoftDeletes;

    private static ?bool $unreadTrackingReady = null;

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

    public function reads(): HasMany
    {
        return $this->hasMany(CommunityPostRead::class, 'community_post_id');
    }

    public function scopeUnreadFor(Builder $query, User $user): Builder
    {
        if (! self::unreadTrackingReady()) {
            // Permite desplegar el código y ejecutar la migración inmediatamente
            // después sin provocar un 500 en una petición que llegue entre ambos.
            return $query->whereRaw('1 = 0');
        }

        $baseline = $user->forum_unread_baseline_at ?? now();

        return $query
            ->where('community_posts.updated_at', '>', $baseline)
            ->whereDoesntHave(
                'reads',
                fn (Builder $reads): Builder => $reads
                    ->where('user_id', $user->id)
                    ->whereColumn(
                        'community_post_reads.read_at',
                        '>=',
                        'community_posts.updated_at',
                    ),
            );
    }

    public function scopeWithReadStateFor(Builder $query, User $user): Builder
    {
        if (! self::unreadTrackingReady()) {
            if ($query->getQuery()->columns === null) {
                $query->select('community_posts.*');
            }

            return $query->addSelect(DB::raw('1 as is_currently_read'));
        }

        return $query->withExists([
            'reads as is_currently_read' => fn (Builder $reads): Builder => $reads
                ->where('user_id', $user->id)
                ->whereColumn(
                    'community_post_reads.read_at',
                    '>=',
                    'community_posts.updated_at',
                ),
        ]);
    }

    public function markReadBy(User $user): void
    {
        if (! self::unreadTrackingReady()) {
            return;
        }

        $seenVersion = $this->updated_at?->copy() ?? now();

        $read = CommunityPostRead::query()->firstOrNew([
            'community_post_id' => $this->id,
            'user_id' => $user->id,
        ]);

        if (
            $read->exists
            && $read->read_at
            && $read->read_at->greaterThanOrEqualTo($seenVersion)
        ) {
            return;
        }

        $read->read_at = $seenVersion;
        $read->save();
    }

    public function reactions(): MorphMany
    {
        return $this->morphMany(CommunityReaction::class, 'reactable');
    }

    private static function unreadTrackingReady(): bool
    {
        return self::$unreadTrackingReady ??= (
            Schema::hasTable('community_post_reads')
            && Schema::hasColumn('users', 'forum_unread_baseline_at')
        );
    }
}
