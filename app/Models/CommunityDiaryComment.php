<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommunityDiaryComment extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'community_diary_id',
        'user_id',
        'body',
    ];

    protected $touches = ['diary'];

    public function diary()
    {
        return $this->belongsTo(CommunityDiary::class, 'community_diary_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}
