<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClipYearContest extends Model
{
    protected $fillable = ['year','enabled','nomination_starts_at','nomination_ends_at','voting_starts_at','voting_ends_at'];
    protected function casts(): array { return [
        'enabled'=>'boolean', 'nomination_starts_at'=>'datetime', 'nomination_ends_at'=>'datetime',
        'voting_starts_at'=>'datetime', 'voting_ends_at'=>'datetime',
    ]; }
    public function nominations() { return $this->hasMany(ClipYearNomination::class, 'contest_id'); }
    public function phase(): string
    {
        if (! $this->enabled) return 'disabled';
        if (now()->betweenIncluded($this->nomination_starts_at, $this->nomination_ends_at)) return 'nominations';
        if (now()->betweenIncluded($this->voting_starts_at, $this->voting_ends_at)) return 'voting';
        return now()->gt($this->voting_ends_at) ? 'results' : 'upcoming';
    }
    public static function current(): ?self
    {
        return self::query()->where('enabled',true)->where('nomination_starts_at','<=',now())->orderByDesc('year')->first();
    }
}
