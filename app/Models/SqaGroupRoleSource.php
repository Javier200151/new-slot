<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SqaGroupRoleSource extends Model
{
    protected $fillable = [
        'sqa_group_id',
        'user_id',
        'role_id',
    ];
}
