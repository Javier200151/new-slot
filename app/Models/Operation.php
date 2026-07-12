<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Operation extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'operations';

    protected $fillable = [
        'operation_type_id',
        'operation_status_id',
        'campaign_id',
        'name',
        'image',
        'description',
        'radio',
        'orbat',
        'ocap',
        'respawn',
        'jip',
        'day_id',
        'pbo',
        'addons',
        'created_by',
        'updated_by',
        'map_id',
        'period_id',
        'editor_id',
        'day_or_night',
    ];

    protected function casts(): array
    {
        return [
            'ocap' => 'boolean',
            'respawn' => 'boolean',
            'jip' => 'boolean',
            'orbat' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($operation) {
            if (Auth::check()) {
                $operation->created_by = Auth::id();
                $operation->updated_by = Auth::id();
            }
        });

        static::updating(function ($operation) {
            if (Auth::check()) {
                $operation->updated_by = Auth::id();
            }
        });
    }

    public function operationType()
    {
        return $this->belongsTo(OperationType::class, 'operation_type_id');
    }

    public function operationStatus()
    {
        return $this->belongsTo(OperationStatus::class, 'operation_status_id');
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function day()
    {
        return $this->belongsTo(OperationDay::class, 'day_id');
    }

    public function map()
    {
        return $this->belongsTo(GameMap::class, 'map_id');
    }

    public function period()
    {
        return $this->belongsTo(Period::class, 'period_id');
    }

    public function enemyFactions()
    {
        return $this->belongsToMany(
            Faction::class,
            'enemy_faction_operation',
            'operation_id',
            'faction_id'
        );
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'editor_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getOrbatSummaryHtml(): HtmlString
    {
        $groups = $this->orbat['groups'] ?? [];

        if (blank($groups)) {
            return new HtmlString('<div class="text-sm text-gray-500 dark:text-gray-400">Esta operación todavía no tiene ORBAT.</div>');
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

        $html = '<div class="space-y-4">';

        foreach ($groups as $group) {
            $groupName = e($group['name'] ?? 'Grupo sin nombre');
            $armyName = e($armyNames[(int) ($group['army_id'] ?? 0)] ?? 'Sin ejército');
            $visibility = ($group['visible'] ?? false) ? 'Visible' : 'Oculto';
            $slots = $group['slots'] ?? [];

            $html .= '<section style="border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem;">';
            $html .= '<table style="border-collapse: collapse;">';
            $html .= '<tbody>';
            $html .= '<tr>';
            $html .= "<td style=\"font-weight: 600; padding-right: 2rem; padding-bottom: 0.5rem;\">{$groupName}</td>";
            $html .= "<td style=\"font-weight: 600; padding-right: 2rem; padding-bottom: 0.5rem;\">{$armyName}</td>";
            //$html .= "<td style=\"font-weight: 600; padding-bottom: 0.5rem;\">{$visibility}</td>";
            $html .= '</tr>';

            if (blank($slots)) {
                $html .= '<tr><td colspan="3" style="color: #6b7280; font-size: 0.875rem;">Sin slots.</td></tr>';
            } else {
                foreach ($slots as $slot) {
                    $slotName = e($slot['name'] ?? 'Slot sin nombre');
                    $slotTypeName = e($slotTypeNames[(int) ($slot['slot_type_id'] ?? 0)] ?? 'Sin tipo');

                    $html .= '<tr>';
                    $html .= "<td style=\"padding-right: 2rem; padding-bottom: 0.25rem;\">{$slotName}</td>";
                    $html .= "<td style=\"padding-right: 2rem; padding-bottom: 0.25rem;\">{$slotTypeName}</td>";
                    $html .= '<td></td>';
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logAll();
    }
}
