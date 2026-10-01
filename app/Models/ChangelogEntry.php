<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
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
