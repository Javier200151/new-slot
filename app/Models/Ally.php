<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class Ally extends Model
{
    use Auditable;
    protected $fillable = [
        'name',
        'image',
        'url',
    ];
}
