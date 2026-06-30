<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class MetopaUser extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'metopa_user';

    protected $primaryKey = 'metopa_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'metopa_id',
        'user_id',
        'assigned_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    protected function setKeysForSaveQuery($query)
    {
        return $query
            ->where('metopa_id', $this->getAttribute('metopa_id'))
            ->where('user_id', $this->getAttribute('user_id'));
    }

    public function metopa()
    {
        return $this->belongsTo(Metopa::class, 'metopa_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logAll();
    }
}