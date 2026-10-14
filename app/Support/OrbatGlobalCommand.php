<?php

namespace App\Support;

use App\Models\ActivityType;
use App\Models\SlotType;
use Illuminate\Support\Str;

class OrbatGlobalCommand
{
    public static function requiredSlotTypeId(?ActivityType $activityType): ?int
    {
        $id = $activityType?->required_orbat_slot_type_id;

        return filled($id) ? (int) $id : null;
    }

    public static function requiredSlotType(?ActivityType $activityType): ?SlotType
    {
        if (! $activityType || ! self::requiredSlotTypeId($activityType)) {
            return null;
        }

        if ($activityType->relationLoaded('requiredOrbatSlotType')) {
            return $activityType->requiredOrbatSlotType;
        }

        return SlotType::query()->find(self::requiredSlotTypeId($activityType));
    }

    public static function hasRequirement(?ActivityType $activityType): bool
    {
        return self::requiredSlotTypeId($activityType) !== null;
    }

    /**
     * Inicializa únicamente el slot obligatorio que falte, sin alterar los demás.
     * Se utiliza tanto al crear como al actualizar una actividad.
     */
    public static function ensureRequiredSlot(?array $orbat, ?ActivityType $activityType): array
    {
        $orbat = is_array($orbat) ? $orbat : ['groups' => []];
        $orbat['groups'] = is_array($orbat['groups'] ?? null) ? $orbat['groups'] : [];

        if (! ($activityType?->usesOrbat() ?? false)
            || ! self::hasRequirement($activityType)
            || self::count($orbat, $activityType) > 0) {
            return $orbat;
        }

        $slot = [
            'slot_key' => (string) Str::ulid(),
            'name' => self::requiredSlotName($activityType),
            'slot_type_id' => self::requiredSlotTypeId($activityType),
            'slot_quick_name_id' => null,
            'visible' => true,
        ];

        // Un grupo ya existente evita generar grupos innecesarios.
        foreach ($orbat['groups'] as $index => $group) {
            if (is_array($group)) {
                $orbat['groups'][$index]['slots'] = is_array($group['slots'] ?? null)
                    ? $group['slots'] : [];
                $orbat['groups'][$index]['slots'][] = $slot;
                return $orbat;
            }
        }

        $orbat['groups'][] = [
            'name' => 'Mando',
            'faction_id' => null,
            'visible' => true,
            'slots' => [$slot],
        ];

        return $orbat;
    }

    public static function count(array $orbat, ?ActivityType $activityType): int
    {
        $requiredSlotTypeId = self::requiredSlotTypeId($activityType);

        if (! $requiredSlotTypeId) {
            return 0;
        }

        return collect($orbat['groups'] ?? [])
            ->flatMap(fn (array $group): array => $group['slots'] ?? [])
            ->filter(
                fn (array $slot): bool => (int) ($slot['slot_type_id'] ?? 0) === $requiredSlotTypeId
            )
            ->count();
    }

    public static function countEditorGroups(array $groups, ?ActivityType $activityType): int
    {
        $requiredSlotTypeId = self::requiredSlotTypeId($activityType);

        if (! $requiredSlotTypeId) {
            return 0;
        }

        return collect($groups)
            ->flatMap(fn (array $group): array => $group['slots'] ?? [])
            ->filter(function (array $slot) use ($requiredSlotTypeId): bool {
                $resolved = SlotQuickSelection::resolveChoice(
                    isset($slot['slot_choice']) && is_string($slot['slot_choice'])
                        ? $slot['slot_choice']
                        : null
                );

                $slotTypeId = $resolved['slot_type_id']
                    ?? (isset($slot['slot_type_id']) ? (int) $slot['slot_type_id'] : 0);

                return (int) $slotTypeId === $requiredSlotTypeId;
            })
            ->count();
    }

    public static function requiredSlotName(?ActivityType $activityType): string
    {
        return self::requiredSlotType($activityType)?->name ?: 'slot obligatorio';
    }

    public static function validationMessage(?ActivityType $activityType): string
    {
        $name = self::requiredSlotName($activityType);

        return "El ORBAT debe contener como mínimo un slot de tipo {$name}.";
    }

    public static function secondSlotQuestion(?ActivityType $activityType): string
    {
        $name = self::requiredSlotName($activityType);

        return "¿Estás seguro de que quieres colocar un segundo {$name}?";
    }
}
