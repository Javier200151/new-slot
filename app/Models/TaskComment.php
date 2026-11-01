<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

use Illuminate\Database\Eloquent\Model;

class TaskComment extends Model
{
    use Auditable;

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
