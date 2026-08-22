<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class RadioModel extends Model
{
    use Auditable;
    protected $fillable = [
        'name',
        'description',
        'channel',
        'block',
        'frequency',
    ];

    protected function casts(): array
    {
        return [
            'channel' => 'boolean',
            'block' => 'boolean',
            'frequency' => 'boolean',
        ];
    }
}
