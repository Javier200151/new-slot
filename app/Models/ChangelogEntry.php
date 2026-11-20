<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Services\ChangelogNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ChangelogEntry extends Model
{
    use Auditable;

    protected $fillable = [
        'version',
        'release_date',
        'is_published',
        'published_at',
        'notified_at',
        'changes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'release_date' => 'date',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'notified_at' => 'datetime',
            'changes' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ChangelogEntry $entry): void {
            if (Auth::check()) {
                $entry->created_by ??= Auth::id();
                $entry->updated_by = Auth::id();
            }
        });

        static::updating(function (ChangelogEntry $entry): void {
            if (Auth::check()) {
                $entry->updated_by = Auth::id();
            }
        });

        static::saving(function (ChangelogEntry $entry): void {
            if ($entry->is_published && ! $entry->published_at) {
                $entry->published_at = now();
            }
        });

        // Avisar solo después del commit: nunca notificar un changelog no guardado.
        static::saved(function (ChangelogEntry $entry): void {
            if (! $entry->is_published || $entry->notified_at
                || ! $entry->published_at || $entry->published_at->isFuture()) {
                return;
            }

            $entryId = $entry->getKey();
            $notify = static function () use ($entryId): void {
                app(ChangelogNotificationService::class)->notifyEntry((int) $entryId);
            };

            if (DB::transactionLevel() > 0) {
                DB::afterCommit($notify);
            } else {
                $notify();
            }
        });
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }
}
