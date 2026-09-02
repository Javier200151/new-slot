<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use App\Models\Concerns\Auditable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Event extends Model
{
    use SoftDeletes, Auditable;

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
        'multiclans',
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
            'multiclans' => 'boolean',
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

    public function media()
    {
        return $this->hasMany(EventMedia::class);
    }

    public function slots()
    {
        return $this->hasMany(EventSlot::class);
    }

    public function isCancelled(): bool
    {
        return mb_strtoupper(
            trim((string) $this->eventStatus?->name)
        ) === 'CANCELADO';
    }

    /**
     * Repara snapshots ORBAT antiguos que todavía no tenían slot_key.
     *
     * Primero reutiliza la clave de un EventSlot existente con los mismos
     * datos para no perder asignaciones; solo genera una ULID nueva para los
     * slots que nunca tuvieron un registro asociado.
     */
    public function ensureOrbatSlotKeys(): bool
    {
        $orbat = $this->orbat ?? ['groups' => []];
        $groups = $orbat['groups'] ?? [];

        if ($groups === []) {
            return false;
        }

        $seenKeys = collect();
        $needsRepair = false;

        foreach ($groups as $group) {
            foreach (($group['slots'] ?? []) as $slot) {
                $slotKey = trim((string) ($slot['slot_key'] ?? ''));

                if ($slotKey === '' || $seenKeys->contains($slotKey)) {
                    $needsRepair = true;
                    break 2;
                }

                $seenKeys->push($slotKey);
            }
        }

        if (! $needsRepair) {
            return false;
        }

        $existingSlots = $this->slots()
            ->get([
                'id',
                'event_id',
                'slot_key',
                'name',
                'slot_type_id',
                'slot_group',
                'faction_id',
            ]);

        $usedKeys = collect();
        $changed = false;

        foreach ($groups as $groupIndex => $group) {
            $groupName = (string) ($group['name'] ?? '');
            $factionId = (int) ($group['faction_id'] ?? 0);

            foreach (($group['slots'] ?? []) as $slotIndex => $slot) {
                $slotKey = trim((string) ($slot['slot_key'] ?? ''));

                if ($slotKey !== '' && ! $usedKeys->contains($slotKey)) {
                    $usedKeys->push($slotKey);
                    continue;
                }

                $slotName = (string) ($slot['name'] ?? '');
                $slotTypeId = (int) ($slot['slot_type_id'] ?? 0);

                $matched = $existingSlots
                    ->first(
                        fn ($candidate): bool =>
                            ! $usedKeys->contains((string) $candidate->slot_key)
                            && (string) $candidate->slot_group === $groupName
                            && (string) $candidate->name === $slotName
                            && (int) $candidate->slot_type_id === $slotTypeId
                            && (
                                $factionId < 1
                                || (int) $candidate->faction_id === $factionId
                            )
                    );

                $slotKey = $matched?->slot_key
                    ?: (string) Str::ulid();

                $groups[$groupIndex]['slots'][$slotIndex]['slot_key'] =
                    (string) $slotKey;

                $usedKeys->push((string) $slotKey);
                $changed = true;
            }
        }

        if (! $changed) {
            return false;
        }

        $orbat['groups'] = $groups;

        DB::table($this->getTable())
            ->where('id', $this->getKey())
            ->update([
                'orbat' => json_encode(
                    $orbat,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
            ]);

        $this->setAttribute('orbat', $orbat);

        return true;
    }

    public function getOrbatSlotsCount(): int
    {
        return collect(
            $this->orbat['groups'] ?? []
        )
            /*
            * Solo grupos visibles.
            */
            ->filter(
                fn (array $group): bool =>
                    (bool) (
                        $group['visible']
                        ?? true
                    )
            )

            /*
            * Contamos únicamente los slots
            * visibles de esos grupos.
            */
            ->sum(
                fn (array $group): int =>
                    collect(
                        $group['slots']
                        ?? []
                    )
                        ->filter(
                            fn (array $slot): bool =>
                                (bool) (
                                    $slot['visible']
                                    ?? true
                                )
                        )
                        ->count()
            );
    }

    public function getOrbatSummaryHtml(): HtmlString
    {
        $groups = $this->orbat['groups'] ?? [];

        if (blank($groups)) {
            return new HtmlString('<div style="color: #6b7280; font-size: 0.875rem;">Este evento todavía no tiene ORBAT.</div>');
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

        $html = '<div style="display: grid; gap: 1rem;">';

        foreach ($groups as $group) {
            $groupName = e($group['name'] ?? 'Grupo sin nombre');
            $factionName = e($factionNames[(int) ($group['faction_id'] ?? 0)] ?? 'Sin facción');
            $groupVisibility = ($group['visible'] ?? false) ? 'Visible' : 'Oculto';
            $slots = $group['slots'] ?? [];

            $html .= '<section style="border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem;">';
            $html .= '<table style="border-collapse: collapse;">';
            $html .= '<tbody>';
            $html .= '<tr>';
            $html .= "<td style=\"font-weight: 600; padding-right: 2rem; padding-bottom: 0.5rem;\">{$groupName}</td>";
            $html .= "<td style=\"font-weight: 600; padding-right: 2rem; padding-bottom: 0.5rem;\">{$factionName}</td>";
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
    public function getAvailableVisibleSlotsCount(): int
    {
        $visibleSlotKeys = collect(
            $this->orbat['groups'] ?? []
        )
            ->filter(
                fn (array $group): bool =>
                    (bool) ($group['visible'] ?? true)
            )
            ->flatMap(
                fn (array $group) =>
                    collect($group['slots'] ?? [])
                        ->filter(
                            fn (array $slot): bool =>
                                (bool) (
                                    $slot['visible'] ?? true
                                )
                        )
                        ->pluck('slot_key')
            )
            ->filter()
            ->unique()
            ->values();

        if ($visibleSlotKeys->isEmpty()) {
            return 0;
        }

        $eventSlots = $this->relationLoaded('slots')
            ? $this->slots
            : $this->slots()->get();

        $occupiedSlots = $eventSlots
            ->filter(
                fn ($slot): bool =>
                    $visibleSlotKeys->contains(
                        $slot->slot_key
                    )
                    && (
                        $slot->user_id !== null
                        || $slot->ally_id !== null
                    )
            )
            ->pluck('slot_key')
            ->unique()
            ->count();

        return max(
            0,
            $visibleSlotKeys->count()
                - $occupiedSlots
        );
    }
}
