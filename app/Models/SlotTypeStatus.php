<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlotTypeStatus extends Model
{
    protected $table = 'slot_types_status';

    public $timestamps = false;

    protected $fillable = [
        'slot_type_id',
        'status_id',
    ];

    public function slotType()
    {
        return $this->belongsTo(SlotType::class);
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }
}
