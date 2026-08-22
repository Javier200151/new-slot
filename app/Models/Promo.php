<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use App\Models\Concerns\Auditable;

class Promo extends Model
{
    use LogsActivity, Auditable;

    protected $table = 'promo';

    protected $fillable = [
        'id',
        'image',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'promo_id');
    }
    
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logAll();
    }
}