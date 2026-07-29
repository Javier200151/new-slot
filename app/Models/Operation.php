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
        'platform_id',
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
            'description' => 'array',
            'radio' => 'array',
            'orbat' => 'array',
            'addons' => 'array',
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

    public function platform()
    {
        return $this->belongsTo(Platform::class, 'platform_id');
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

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    public function getOrbatSummaryHtml(): HtmlString
    {
        $groups = $this->orbat['groups'] ?? [];

        if (blank($groups)) {
            return new HtmlString('<div class="text-sm text-gray-500 dark:text-gray-400">Esta operación todavía no tiene ORBAT.</div>');
        }

        $factionNames = Faction::query()
            ->whereIn('id', collect($groups)->pluck('faction_id')->filter()->unique())
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
            $factionName = e($factionNames[(int) ($group['faction_id'] ?? 0)] ?? 'Sin facción');
            $visibility = ($group['visible'] ?? false) ? 'Visible' : 'Oculto';
            $slots = $group['slots'] ?? [];

            $html .= '<section style="border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem;">';
            $html .= '<table style="border-collapse: collapse;">';
            $html .= '<tbody>';
            $html .= '<tr>';
            $html .= "<td style=\"font-weight: 600; padding-right: 2rem; padding-bottom: 0.5rem;\">{$groupName}</td>";
            $html .= "<td style=\"font-weight: 600; padding-right: 2rem; padding-bottom: 0.5rem;\">{$factionName}</td>";
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

    public function getDescriptionSummaryHtml(): HtmlString
    {
        $sections = $this->description['sections'] ?? [];

        if (blank($sections) && filled($this->description['content'] ?? null)) {
            $sections = [
                [
                    'title' => 'Descripción',
                    'content' => $this->description['content'],
                ],
            ];
        }

        if (blank($sections)) {
            return new HtmlString('<div style="color: #6b7280; font-size: 0.875rem;">Esta operación todavía no tiene descripción.</div>');
        }

        $html = '<div style="display: grid; gap: 1rem;">';

        foreach ($sections as $section) {
            $title = e($section['title'] ?? 'Sección sin título');
            $content = $section['content'] ?? '';

            $html .= '<section style="border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem;">';
            $html .= "<h3 style=\"font-size: 1.125rem; font-weight: 700; margin: 0 0 0.75rem;\">{$title}</h3>";

            if (filled($content)) {
                $html .= "<div style=\"margin-bottom: 1rem;\">{$content}</div>";
            }

            $html .= '</section>';
        }

        $html .= '</div>';

        return new HtmlString($html);
    }

    public function getAddonsSummaryHtml(): HtmlString
    {
        $addonIds = $this->addons['addon_ids'] ?? [];

        if (blank($addonIds) && filled($this->addons['content'] ?? null)) {
            return new HtmlString(e($this->addons['content']));
        }

        if (blank($addonIds)) {
            return new HtmlString('<div style="color: #6b7280; font-size: 0.875rem;">Esta operación todavía no tiene addons.</div>');
        }

        $addons = Addon::query()
            ->whereIn('id', $addonIds)
            ->orderBy('mandatory', 'desc')
            ->orderBy('name')
            ->get();

        if ($addons->isEmpty()) {
            return new HtmlString('<div style="color: #6b7280; font-size: 0.875rem;">Los addons seleccionados ya no existen.</div>');
        }

        $html = '<ul style="display: grid; gap: 0.5rem; list-style: none; margin: 0; padding: 0;">';

        foreach ($addons as $addon) {
            $name = e($addon->name);
            $description = e($addon->description ?? '');
            $mandatory = $addon->mandatory
                ? '<span style="background: #fee2e2; border-radius: 9999px; color: #991b1b; font-size: 0.75rem; padding: 0.125rem 0.5rem;">Obligatorio</span>'
                : '<span style="background: #e5e7eb; border-radius: 9999px; color: #374151; font-size: 0.75rem; padding: 0.125rem 0.5rem;">Opcional</span>';

            $html .= '<li style="border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 0.75rem;">';
            $html .= "<div style=\"align-items: center; display: flex; gap: 0.5rem; justify-content: space-between;\"><strong>{$name}</strong>{$mandatory}</div>";

            if (filled($description)) {
                $html .= "<div style=\"color: #6b7280; font-size: 0.875rem; margin-top: 0.25rem;\">{$description}</div>";
            }

            $html .= '</li>';
        }

        $html .= '</ul>';

        return new HtmlString($html);
    }

    public function getRadioSummaryHtml(): HtmlString
    {
        $networks = $this->radio['networks'] ?? [];

        if (blank($networks) && filled($this->radio['content'] ?? null)) {
            return new HtmlString(e($this->radio['content']));
        }

        if (blank($networks)) {
            return new HtmlString('<div style="color: #6b7280; font-size: 0.875rem;">Esta operación todavía no tiene radios.</div>');
        }

        $html = '<table style="border-collapse: collapse; width: 100%;">';
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th style="border-bottom: 1px solid #e5e7eb; padding: 0.5rem; text-align: left;">Red</th>';
        $html .= '<th style="border-bottom: 1px solid #e5e7eb; padding: 0.5rem; text-align: left;">Radio</th>';
        $html .= '<th style="border-bottom: 1px solid #e5e7eb; padding: 0.5rem; text-align: left;">Configuración</th>';
        $html .= '<th style="border-bottom: 1px solid #e5e7eb; padding: 0.5rem; text-align: left;">Notas</th>';
        $html .= '</tr>';
        $html .= '</thead><tbody>';

        foreach ($networks as $network) {
            $name = e($network['name'] ?? 'Red sin nombre');
            $radioModel = e($network['radio_model_name'] ?? 'Sin radio');
            $notes = e($network['notes'] ?? '');
            $visible = array_key_exists('visible', $network) && ! $network['visible']
                ? '<div style="color: #6b7280; font-size: 0.75rem;">Oculta</div>'
                : '';

            $configuration = collect($network['configuration'] ?? [])
                ->filter(fn ($value): bool => filled($value))
                ->map(function ($value, string $key): string {
                    $label = match ($key) {
                        'channel' => 'Canal',
                        'block' => 'Bloque',
                        'frequency' => 'Frecuencia',
                        default => ucfirst($key),
                    };

                    if ($key === 'frequency') {
                        $value .= ' MHz';
                    }

                    return e("{$label}: {$value}");
                })
                ->implode('<br>');

            $html .= '<tr>';
            $html .= "<td style=\"border-bottom: 1px solid #f3f4f6; padding: 0.5rem;\"><strong>{$name}</strong>{$visible}</td>";
            $html .= "<td style=\"border-bottom: 1px solid #f3f4f6; padding: 0.5rem;\">{$radioModel}</td>";
            $html .= "<td style=\"border-bottom: 1px solid #f3f4f6; padding: 0.5rem;\">{$configuration}</td>";
            $html .= "<td style=\"border-bottom: 1px solid #f3f4f6; padding: 0.5rem;\">{$notes}</td>";
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return new HtmlString($html);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logAll();
    }
}
