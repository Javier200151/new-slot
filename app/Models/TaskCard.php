<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskCard extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_WORKING = 'working';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'title',
        'description',
        'status',
        'position',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_WORKING => 'Trabajando',
            self::STATUS_COMPLETED => 'Completado',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function assignees()
    {
        return $this->belongsToMany(User::class, 'task_card_user')
            ->withPivot('assigned_by')
            ->withTimestamps();
    }

    public function checklistItems()
    {
        return $this->hasMany(TaskChecklistItem::class)
            ->orderBy('position')
            ->orderBy('id');
    }

    public function comments()
    {
        return $this->hasMany(TaskComment::class)
            ->orderBy('created_at')
            ->orderBy('id');
    }
}
