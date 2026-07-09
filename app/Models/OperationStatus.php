<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationStatus extends Model
{
    protected $table = 'operation_status';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'description',
    ];
}
