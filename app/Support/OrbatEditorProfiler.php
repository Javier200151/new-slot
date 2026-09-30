<?php

namespace App\Support;

class OrbatEditorProfiler
{
    public static function analyze(array $orbat): array
    {
        $groups = $orbat['groups'] ?? [];
        $groupCount = 0;
        $slotCount = 0;
        $visibleGroups = 0;
        $visibleSlots = 0;
        $slotTypeIds = [];

        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }

            $groupCount++;

            if ((bool) ($group['visible'] ?? true)) {
                $visibleGroups++;
            }

            foreach (($group['slots'] ?? []) as $slot) {
                if (! is_array($slot)) {
                    continue;
                }

                $slotCount++;

                if ((bool) ($slot['visible'] ?? true)) {
                    $visibleSlots++;
                }

                $slotTypeId = (int) ($slot['slot_type_id'] ?? 0);
                if ($slotTypeId > 0) {
                    $slotTypeIds[$slotTypeId] = true;
                }
            }
        }

        $encoded = json_encode($orbat, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return [
            'groups' => $groupCount,
            'visible_groups' => $visibleGroups,
            'slots' => $slotCount,
            'visible_slots' => $visibleSlots,
            'hidden_slots' => max(0, $slotCount - $visibleSlots),
            'slot_types' => count($slotTypeIds),
            'json_bytes' => is_string($encoded) ? strlen($encoded) : 0,
            // Aproximación útil para detectar ORBAT que harán crecer mucho el
            // árbol de componentes de Filament antes de abrir el modal.
            'estimated_filament_components' => ($groupCount * 6) + ($slotCount * 5),
        ];
    }


    public static function iniBytes(string $value): ?int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return $value === '-1' ? -1 : null;
        }

        if (! preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*([KMG]?)$/i', $value, $matches)) {
            return null;
        }

        $number = (float) $matches[1];
        $unit = strtoupper((string) ($matches[2] ?? ''));
        $multiplier = match ($unit) {
            'G' => 1024 ** 3,
            'M' => 1024 ** 2,
            'K' => 1024,
            default => 1,
        };

        return (int) round($number * $multiplier);
    }

    public static function bytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1) . ' KiB';
        }

        return number_format($bytes / 1024 / 1024, 1) . ' MiB';
    }
}
