<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Metopa extends Model
{
    use SoftDeletes;

    protected $table = 'metopas';

    protected $fillable = [
        'name',
        'description',
        'image',
        'image_large',
        'created_by',
        'updated_by',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'metopa_user');
    }
}