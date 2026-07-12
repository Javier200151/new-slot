<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlotType extends Model
{
    protected $table = 'slot_types';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'description',
    ];

    public function slotTypeStatuses()
    {
        return $this->hasMany(SlotTypeStatus::class);
    }

    public function statuses()
    {
        return $this->belongsToMany(
            Status::class,
            'slot_types_status',
            'slot_type_id',
            'status_id'
        );
    }
}
