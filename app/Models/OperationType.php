<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class OperationType extends Model
{
    use Auditable;
    protected $table = 'operations_type';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'description',
        'oficial',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'oficial' => 'boolean',
        ];
    }
}
