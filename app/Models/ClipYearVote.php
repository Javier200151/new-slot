<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

use Illuminate\Database\Eloquent\Model;

class ClipYearVote extends Model
{
    use Auditable;

    protected $fillable = ['nomination_id','user_id','skulls'];
    public function nomination() { return $this->belongsTo(ClipYearNomination::class,'nomination_id'); }
}
