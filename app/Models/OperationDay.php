<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class OperationDay extends Model
{
    use Auditable;
    protected $table = 'operation_day';

    public $timestamps = false;

    protected $fillable = [
        'name',

    ];
}
