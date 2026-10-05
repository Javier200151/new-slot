<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use App\Models\Concerns\Auditable;
use App\Services\ProtectedAdminGuard;

class SqaGroupUser extends Model
{
    use SoftDeletes, Auditable;

    protected $fillable = [
        'sqa_group_id',
        'user_id',
        'main',
        'coordinator',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'main' => 'boolean',
            'coordinator' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SqaGroupUser $sqaGroupUser): void {
            app(ProtectedAdminGuard::class)->authorizeUserId((int) $sqaGroupUser->user_id);
            static::guardCoordinatorReplacement($sqaGroupUser);
        });

        static::updating(function (SqaGroupUser $sqaGroupUser): void {
            app(ProtectedAdminGuard::class)->authorizeUserId(
                (int) ($sqaGroupUser->getOriginal('user_id') ?: $sqaGroupUser->user_id)
            );
            static::guardCoordinatorReplacement($sqaGroupUser);
        });

        static::deleting(function (SqaGroupUser $sqaGroupUser): void {
            app(ProtectedAdminGuard::class)->authorizeUserId((int) $sqaGroupUser->user_id);
        });

        static::restoring(function (SqaGroupUser $sqaGroupUser): void {
            app(ProtectedAdminGuard::class)->authorizeUserId((int) $sqaGroupUser->user_id);
        });

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
            if ($sqaGroupUser->main) {
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
            }

            if (! $sqaGroupUser->coordinator) {
                return;
            }

            $otherCoordinators = static::query()
                ->where('sqa_group_id', $sqaGroupUser->sqa_group_id)
                ->whereKeyNot($sqaGroupUser->id)
                ->where('coordinator', true)
                ->get();

            foreach ($otherCoordinators as $otherCoordinator) {
                $otherCoordinator->forceFill([
                    'coordinator' => false,
                    'updated_by' => Auth::id(),
                ])->save();
            }
        });
    }

    private static function guardCoordinatorReplacement(SqaGroupUser $candidate): void
    {
        if (! $candidate->coordinator || ! $candidate->sqa_group_id) {
            return;
        }

        $existingCoordinator = static::query()
            ->where('sqa_group_id', $candidate->sqa_group_id)
            ->where('coordinator', true)
            ->when(
                $candidate->exists,
                fn ($query) => $query->whereKeyNot($candidate->getKey())
            )
            ->first();

        if ($existingCoordinator) {
            app(ProtectedAdminGuard::class)->authorizeUserId((int) $existingCoordinator->user_id);
        }
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
