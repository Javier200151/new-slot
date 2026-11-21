<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Audit actions from the website. The agent keeps its own independent audit file. */
class GameServerActionLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'actor', 'server_id', 'action', 'subject', 'result', 'detail', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
