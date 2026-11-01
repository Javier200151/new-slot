<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityClipLike extends Model
{
    protected $fillable = ['community_clip_id', 'user_id'];
    public function clip() { return $this->belongsTo(CommunityClip::class, 'community_clip_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
