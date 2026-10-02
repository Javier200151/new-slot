<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class UserStatusHistory extends Model
{
    use Auditable;

    protected $fillable = [
        'user_id',
        'from_status_id',
        'to_status_id',
        'changed_at',
        'changed_by_user_id',
        'source',
        'source_hash',
        'retutored_by',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function fromStatus()
    {
        return $this->belongsTo(Status::class, 'from_status_id')->withTrashed();
    }

    public function toStatus()
    {
        return $this->belongsTo(Status::class, 'to_status_id')->withTrashed();
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by_user_id')->withTrashed();
    }
}
