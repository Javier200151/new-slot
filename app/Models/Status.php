<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class Status extends Model
{
    use SoftDeletes;

    protected $table = 'status';

    public $timestamps = false;

    protected $fillable = [
        'name',
    ];
    public function users()
    {
        return $this->hasMany(User::class, 'status_id');
    }
}