<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class CommunityProcessApplication extends Model
{
    use Auditable;

    protected $fillable = [
        'community_process_id',
        'user_id',
        'body',
        'withdrawn_at',
    ];

    protected function casts(): array
    {
        return [
            'withdrawn_at' => 'datetime',
        ];
    }

    public function process()
    {
        return $this->belongsTo(CommunityProcess::class, 'community_process_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function isWithdrawn(): bool
    {
        return $this->withdrawn_at !== null;
    }
}
