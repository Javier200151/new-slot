<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use App\Models\Concerns\Auditable;

class SqaGroupUser extends Model
{
    use SoftDeletes, Auditable;

    protected $fillable = [
        'sqa_group_id',
        'user_id',
        'main',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'main' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($sqaGroupUser): void {
            if (Auth::check()) {
                $sqaGroupUser->updated_by = Auth::id();
            }
        });

        static::updating(function ($sqaGroupUser): void {
            if (Auth::check()) {
                $sqaGroupUser->updated_by = Auth::id();
            }
        });

        static::saved(function ($sqaGroupUser): void {
            if (! $sqaGroupUser->main) {
                return;
            }

            $otherMainGroups =
                static::query()
                    ->where(
                        'user_id',
                        $sqaGroupUser->user_id
                    )
                    ->whereKeyNot(
                        $sqaGroupUser->id
                    )
                    ->where('main', true)
                    ->get();

            foreach (
                $otherMainGroups
                as $otherGroup
            ) {
                $otherGroup->forceFill([
                    'main' => false,

                    'updated_by' =>
                        Auth::id(),
                ])->save();
            }
        });
    }

    public function sqaGroup()
    {
        return $this->belongsTo(SqaGroup::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
