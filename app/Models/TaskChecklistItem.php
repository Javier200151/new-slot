<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskChecklistItem extends Model
{
    protected $fillable = [
        'task_card_id',
        'label',
        'completed',
        'position',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'completed' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function taskCard()
    {
        return $this->belongsTo(TaskCard::class);
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
