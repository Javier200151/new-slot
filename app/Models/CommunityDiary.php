<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class CommunityDiary extends Model
{
    use Auditable;

    private static ?bool $unreadTrackingReady = null;

    protected $fillable = [
        'user_id',
        'author_nick',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function entries()
    {
        return $this->hasMany(CommunityDiaryEntry::class);
    }

    public function comments()
    {
        return $this->hasMany(CommunityDiaryComment::class)->oldest('created_at');
    }

    public function subscriptions()
    {
        return $this->morphMany(CommunitySubscription::class, 'subscribable');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(CommunityDiaryRead::class, 'community_diary_id');
    }

    public function scopeUnreadFor(Builder $query, User $user): Builder
    {
        if (! self::unreadTrackingReady()) {
            return $query->whereRaw('1 = 0');
        }

        $baseline = $user->diary_unread_baseline_at ?? now();

        return $query
            ->where('community_diaries.updated_at', '>', $baseline)
            ->whereDoesntHave(
                'reads',
                fn (Builder $reads): Builder => $reads
                    ->where('user_id', $user->id)
                    ->whereColumn(
                        'community_diary_reads.read_at',
                        '>=',
                        'community_diaries.updated_at',
                    ),
            );
    }

    public function markReadBy(User $user): void
    {
        if (! self::unreadTrackingReady()) {
            return;
        }

        $seenVersion = $this->updated_at?->copy() ?? now();

        $read = CommunityDiaryRead::query()->firstOrNew([
            'community_diary_id' => $this->id,
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

    private static function unreadTrackingReady(): bool
    {
        return self::$unreadTrackingReady ??= (
            Schema::hasTable('community_diary_reads')
            && Schema::hasColumn('users', 'diary_unread_baseline_at')
        );
    }
}
