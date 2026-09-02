<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class OperationStatus extends Model
{
    use Auditable;
    protected $table = 'operation_status';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'color',
        'description',
    ];
}
