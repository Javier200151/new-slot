<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class Platform extends Model
{
    use Auditable;
    public $timestamps = false;

    protected $fillable = [
        'name',
        'image',
    ];

    public function isReforger(): bool
    {
        return str_contains(
            mb_strtolower(trim((string) $this->name)),
            'reforger',
        );
    }
}
