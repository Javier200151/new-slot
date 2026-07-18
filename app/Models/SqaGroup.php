<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SqaGroup extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'large_name',
        'description',
        'image',
    ];
}
