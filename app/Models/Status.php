<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\Concerns\Auditable;

class Status extends Model
{
    use SoftDeletes, Auditable;

    protected $table = 'status';

    public $timestamps = false;

    protected $fillable = [
        'name',
    ];
    public function users()
    {
        return $this->hasMany(User::class, 'status_id');
    }

    public function slotTypeStatuses()
    {
        return $this->hasMany(SlotTypeStatus::class, 'status_id');
    }

    public function slotTypes()
    {
        return $this->belongsToMany(
            SlotType::class,
            'slot_types_status',
            'status_id',
            'slot_type_id'
        );
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logAll();
    }
}
