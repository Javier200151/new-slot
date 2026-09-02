<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommunityPostComment extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'community_post_id',
        'user_id',
        'body',
    ];

    public function post()
    {
        return $this->belongsTo(CommunityPost::class, 'community_post_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}
