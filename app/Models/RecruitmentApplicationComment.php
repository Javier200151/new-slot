<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class RecruitmentApplicationComment extends Model
{
    use Auditable;

    protected $fillable = [
        'contact_submission_id',
        'user_id',
        'content',
    ];

    public function submission()
    {
        return $this->belongsTo(ContactSubmission::class, 'contact_submission_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}
