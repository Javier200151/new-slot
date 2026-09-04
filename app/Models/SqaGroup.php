<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\Auditable;

class SqaGroup extends Model
{
    use SoftDeletes, Auditable;

    protected $fillable = [
        'name',
        'large_name',
        'description',
        'image',
        'icon',
        'color',
        'display_order',
        'show_in_organization',
        'has_coordinator_role',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'show_in_organization' => 'boolean',
            'has_coordinator_role' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (self $group): void {
            if ($group->has_coordinator_role || ! $group->wasChanged('has_coordinator_role')) {
                return;
            }

            $values = ['coordinator' => false];

            if ($userId = auth()->id()) {
                $values['updated_by'] = $userId;
            }

            $group->sqaGroupUsers()
                ->where('coordinator', true)
                ->update($values);
        });
    }

    public function sqaGroupUsers()
    {
        return $this->hasMany(SqaGroupUser::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'sqa_group_users')
            ->withPivot([
                'main',
                'coordinator',
                'updated_by',
                'deleted_at',
            ])
            ->withTimestamps()
            ->wherePivotNull('deleted_at');
    }

    public function coordinatorAssignment()
    {
        return $this->hasOne(SqaGroupUser::class)
            ->where('coordinator', true);
    }
}
