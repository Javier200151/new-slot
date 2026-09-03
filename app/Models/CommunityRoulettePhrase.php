<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class CommunityRoulettePhrase extends Model
{
    use Auditable;

    protected $fillable = [
        'text',
        'active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function rooms()
    {
        return $this->hasMany(CommunityRouletteRoom::class, 'winner_phrase_id');
    }
}
