<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClipYearNomination extends Model
{
    protected $fillable = ['contest_id','user_id','community_clip_id','description','is_approved'];
    protected function casts(): array { return ['is_approved'=>'boolean']; }
    public function contest() { return $this->belongsTo(ClipYearContest::class,'contest_id'); }
    public function user() { return $this->belongsTo(User::class); }
    public function clip() { return $this->belongsTo(CommunityClip::class,'community_clip_id'); }
    public function votes() { return $this->hasMany(ClipYearVote::class,'nomination_id'); }
}
