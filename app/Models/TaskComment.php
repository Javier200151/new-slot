<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskComment extends Model
{
    protected $fillable = [
        'task_card_id',
        'user_id',
        'body',
    ];

    public function taskCard()
    {
        return $this->belongsTo(TaskCard::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
