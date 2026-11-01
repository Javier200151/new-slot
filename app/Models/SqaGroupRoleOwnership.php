<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

use Illuminate\Database\Eloquent\Model;

class SqaGroupRoleOwnership extends Model
{
    use Auditable;

    protected $fillable = [
        'user_id',
        'role_id',
        'was_preexisting',
    ];

    protected function casts(): array
    {
        return [
            'was_preexisting' => 'boolean',
        ];
    }
}
