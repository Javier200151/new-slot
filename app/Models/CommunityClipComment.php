<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

use Illuminate\Database\Eloquent\Model;

class CommunityClipComment extends Model
{
    use Auditable;

    protected $fillable = ['community_clip_id', 'user_id', 'body'];
    public function clip() { return $this->belongsTo(CommunityClip::class, 'community_clip_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
