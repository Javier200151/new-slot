<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class SlotType extends Model
{
    use Auditable;
    protected $table = 'slot_types';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'description',
        'picker_column',
        'picker_order',
    ];

    protected static function booted(): void
    {
        static::creating(
            function (SlotType $slotType): void {
                if ((int) $slotType->picker_column > 0) {
                    return;
                }

                $counts = collect(range(1, 4))
                    ->mapWithKeys(
                        fn (int $column): array => [
                            $column => static::query()
                                ->where('picker_column', $column)
                                ->count(),
                        ]
                    );

                $targetColumn = (int) $counts
                    ->sort()
                    ->keys()
                    ->first();

                $slotType->picker_column = $targetColumn ?: 1;
                $slotType->picker_order = (
                    (int) static::query()
                        ->where(
                            'picker_column',
                            $slotType->picker_column
                        )
                        ->max('picker_order')
                ) + 10;
            }
        );
    }

    public function quickNames()
    {
        return $this->hasMany(SlotTypeQuickName::class)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

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
