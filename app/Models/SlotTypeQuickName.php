<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\SlotQuickSelection;
use Illuminate\Database\Eloquent\Model;

class SlotTypeQuickName extends Model
{
    use Auditable;

    protected $fillable = [
        'slot_type_id',
        'category',
        'name',
        'shortcut',
        'is_course_student',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_course_student' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(
            function (): void {
                SlotQuickSelection::clearCache();
            }
        );

        static::deleted(
            function (): void {
                SlotQuickSelection::clearCache();
            }
        );
    }

    public function slotType()
    {
        return $this->belongsTo(SlotType::class);
    }
}
