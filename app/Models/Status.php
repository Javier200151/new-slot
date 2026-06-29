<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Status extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'status';

    public $timestamps = false;

    protected $fillable = [
        'name',
    ];
    public function users()
    {
        return $this->hasMany(User::class, 'status_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logAll();
    }
}