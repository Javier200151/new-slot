<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationDay extends Model
{
    protected $table = 'operation_day';

    public $timestamps = false;

    protected $fillable = [
        'name',

    ];
}
