<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClipYearVote extends Model
{
    protected $fillable = ['nomination_id','user_id','skulls'];
    public function nomination() { return $this->belongsTo(ClipYearNomination::class,'nomination_id'); }
}
