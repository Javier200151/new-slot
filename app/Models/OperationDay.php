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
    public function operations()
    {
        return $this->belongsToMany(
            Operation::class,
            'operation_operation_day',
            'operation_day_id',
            'operation_id'
        );
    }
}
