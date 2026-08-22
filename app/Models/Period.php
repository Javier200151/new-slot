<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class Period extends Model
{
    use Auditable;
    protected $fillable = [
        'name',
        'ico',
        'description',
    ];
}
