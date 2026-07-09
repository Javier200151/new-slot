<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationType extends Model
{
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
