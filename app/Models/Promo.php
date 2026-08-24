<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class Promo extends Model
{
    use Auditable;

    protected $table = 'promo';

    protected $fillable = [
        'id',
        'image',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'promo_id');
    }
    
}