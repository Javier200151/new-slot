<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Auth;

class Event extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'operation_id',
        'name',
        'date',
        'end_date',
        'duration',
        'orbat',
        'event_status_id',
        'event_result_id',
        'ocap_url',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
            'end_date' => 'datetime',
            'duration' => 'integer',
            'orbat' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($event): void {
            if (Auth::check()) {
                $event->created_by = Auth::id();
                $event->updated_by = Auth::id();
            }
        });

        static::updating(function ($event): void {
            if (Auth::check()) {
                $event->updated_by = Auth::id();
            }
        });
    }

    public function operation()
    {
        return $this->belongsTo(Operation::class);
    }

    public function eventStatus()
    {
        return $this->belongsTo(EventStatus::class, 'event_status_id');
    }

    public function eventResult()
    {
        return $this->belongsTo(EventResult::class, 'event_result_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function comments()
    {
        return $this->hasMany(EventComment::class);
    }

    public function streams()
    {
        return $this->hasMany(Stream::class);
    }

    public function getOrbatSummaryHtml(): HtmlString
    {
        $groups = $this->orbat['groups'] ?? [];

        if (blank($groups)) {
            return new HtmlString('<div style="color: #6b7280; font-size: 0.875rem;">Este evento todavía no tiene ORBAT.</div>');
        }

        $armyNames = Army::query()
            ->whereIn('id', collect($groups)->pluck('army_id')->filter()->unique())
            ->pluck('name', 'id');

        $slotTypeNames = SlotType::query()
            ->whereIn(
                'id',
                collect($groups)
                    ->flatMap(fn (array $group): array => $group['slots'] ?? [])
                    ->pluck('slot_type_id')
                    ->filter()
                    ->unique()
            )
            ->pluck('name', 'id');

        $html = '<div style="display: grid; gap: 1rem;">';

        foreach ($groups as $group) {
            $groupName = e($group['name'] ?? 'Grupo sin nombre');
            $armyName = e($armyNames[(int) ($group['army_id'] ?? 0)] ?? 'Sin ejército');
            $groupVisibility = ($group['visible'] ?? false) ? 'Visible' : 'Oculto';
            $slots = $group['slots'] ?? [];

            $html .= '<section style="border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem;">';
            $html .= '<table style="border-collapse: collapse;">';
            $html .= '<tbody>';
            $html .= '<tr>';
            $html .= "<td style=\"font-weight: 600; padding-right: 2rem; padding-bottom: 0.5rem;\">{$groupName}</td>";
            $html .= "<td style=\"font-weight: 600; padding-right: 2rem; padding-bottom: 0.5rem;\">{$armyName}</td>";
            $html .= "<td style=\"font-weight: 600; padding-bottom: 0.5rem;\">{$groupVisibility}</td>";
            $html .= '</tr>';

            if (blank($slots)) {
                $html .= '<tr><td colspan="3" style="color: #6b7280; font-size: 0.875rem;">Sin slots.</td></tr>';
            } else {
                foreach ($slots as $slot) {
                    $slotName = e($slot['name'] ?? 'Slot sin nombre');
                    $slotTypeName = e($slotTypeNames[(int) ($slot['slot_type_id'] ?? 0)] ?? 'Sin tipo');
                    $slotVisibility = ($slot['visible'] ?? false) ? 'Visible' : 'Oculto';

                    $html .= '<tr>';
                    $html .= "<td style=\"padding-right: 2rem; padding-bottom: 0.25rem;\">{$slotName}</td>";
                    $html .= "<td style=\"padding-right: 2rem; padding-bottom: 0.25rem;\">{$slotTypeName}</td>";
                    $html .= "<td style=\"padding-bottom: 0.25rem;\">{$slotVisibility}</td>";
                    $html .= '</tr>';
                }
            }

            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</section>';
        }

        $html .= '</div>';

        return new HtmlString($html);
    }
}
