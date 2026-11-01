<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

use Illuminate\Database\Eloquent\Model;

class SqaGroupRoleSource extends Model
{
    use Auditable;

    protected $fillable = [
        'sqa_group_id',
        'user_id',
        'role_id',
    ];
}
