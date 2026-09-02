<?php

namespace App\Support;

use App\Models\SlotType;
use App\Models\SlotTypeQuickName;
use Illuminate\Support\Str;

class SlotQuickSelection
{
    private static ?array $pickerGroupsCache = null;

    private static ?array $choiceMetadataCache = null;

    private static ?array $pickerColumnsCache = null;

    public static function pickerGroups(): array
    {
        self::buildPickerCache();

        return self::$pickerGroupsCache ?? [];
    }

    public static function pickerColumns(): array
    {
        self::buildPickerCache();

        return self::$pickerColumnsCache ?? [1 => [], 2 => [], 3 => [], 4 => []];
    }

    public static function clearCache(): void
    {
        self::$pickerGroupsCache = null;
        self::$choiceMetadataCache = null;
        self::$pickerColumnsCache = null;
    }

    public static function pickerFieldName(string $slotTypeName): string
    {
        return 'slot_picker_' . Str::slug($slotTypeName, '_') . '_' . substr(md5($slotTypeName), 0, 8);
    }

    public static function pickerFormData(?string $choice): array
    {
        $data = [
            'selected_slot_choice' => $choice,
            'slot_search' => '',
        ];

        foreach (array_keys(self::pickerGroups()) as $slotTypeName) {
            $data[self::pickerFieldName($slotTypeName)] = null;
        }

        if (blank($choice)) {
            return $data;
        }

        $metadata = self::choiceMetadata($choice);

        if ($metadata) {
            $data[self::pickerFieldName($metadata['slot_type_name'])] = $choice;
        }

        return $data;
    }

    public static function selectedSummary(mixed $choice): ?string
    {
        if (! is_string($choice) || blank($choice)) {
            return null;
        }

        $metadata = self::choiceMetadata($choice);

        if (! $metadata) {
            return null;
        }

        return $metadata['slot_type_name'] . ' · ' . $metadata['name'];
    }

    /**
     * Se mantiene como alias de compatibilidad por si algún código antiguo
     * todavía consulta las opciones agrupadas.
     */
    public static function options(): array
    {
        return self::pickerGroups();
    }

    public static function selectedLabel(mixed $choice): ?string
    {
        if (! is_string($choice) || blank($choice)) {
            return null;
        }

        return self::choiceMetadata($choice)['slot_type_name'] ?? null;
    }

    public static function prepareOrbat(array $orbat): array
    {
        $groups = $orbat['groups'] ?? [];

        $quickNames = SlotTypeQuickName::query()
            ->get()
            ->groupBy('slot_type_id');

        foreach ($groups as $groupIndex => $group) {
            foreach (($group['slots'] ?? []) as $slotIndex => $slot) {
                $slotTypeId = (int) ($slot['slot_type_id'] ?? 0);
                $quickNameId = (int) ($slot['slot_quick_name_id'] ?? 0);
                $matched = null;

                $choice = $quickNameId > 0
                    ? 'quick:' . $quickNameId
                    : null;

                if (! $choice && $slotTypeId > 0) {
                    $matched = ($quickNames->get($slotTypeId) ?? collect())
                        ->first(
                            fn (SlotTypeQuickName $quickName): bool =>
                                mb_strtolower(trim($quickName->name))
                                === mb_strtolower(trim((string) ($slot['name'] ?? '')))
                        );

                    $choice = $matched
                        ? 'quick:' . $matched->id
                        : 'type:' . $slotTypeId;
                }

                $groups[$groupIndex]['slots'][$slotIndex]['slot_choice'] = $choice;
                $groups[$groupIndex]['slots'][$slotIndex]['slot_quick_name_id'] =
                    $quickNameId > 0 ? $quickNameId : ($matched->id ?? null);
            }
        }

        $orbat['groups'] = $groups;

        return $orbat;
    }

    public static function resolveChoice(?string $choice): array
    {
        if (blank($choice)) {
            return [
                'slot_type_id' => null,
                'slot_quick_name_id' => null,
                'name' => null,
            ];
        }

        if (str_starts_with($choice, 'quick:')) {
            $quickName = SlotTypeQuickName::query()
                ->with('slotType')
                ->find((int) substr($choice, 6));

            if ($quickName) {
                return [
                    'slot_type_id' => (int) $quickName->slot_type_id,
                    'slot_quick_name_id' => (int) $quickName->id,
                    'name' => $quickName->name,
                ];
            }
        }

        if (str_starts_with($choice, 'type:')) {
            $slotType = SlotType::query()
                ->find((int) substr($choice, 5));

            if ($slotType) {
                return [
                    'slot_type_id' => (int) $slotType->id,
                    'slot_quick_name_id' => null,
                    'name' => $slotType->name,
                ];
            }
        }

        return [
            'slot_type_id' => null,
            'slot_quick_name_id' => null,
            'name' => null,
        ];
    }

    public static function isStudentSlot(array $slot): bool
    {
        $quickNameId = (int) ($slot['slot_quick_name_id'] ?? 0);

        if ($quickNameId > 0) {
            return SlotTypeQuickName::query()
                ->whereKey($quickNameId)
                ->where('is_course_student', true)
                ->exists();
        }

        return mb_strtolower(trim((string) ($slot['name'] ?? ''))) === 'alumno';
    }

    private static function choiceMetadata(string $choice): ?array
    {
        self::buildPickerCache();

        return self::$choiceMetadataCache[$choice] ?? null;
    }

    private static function buildPickerCache(): void
    {
        if (
            self::$pickerGroupsCache !== null
            && self::$choiceMetadataCache !== null
            && self::$pickerColumnsCache !== null
        ) {
            return;
        }

        $groups = [];
        $metadata = [];
        $columns = [
            1 => [],
            2 => [],
            3 => [],
            4 => [],
        ];

        /*
        |--------------------------------------------------------------------------
        | El TIPO DE SLOT es el encabezado
        |--------------------------------------------------------------------------
        |
        | No existe una segunda categoría visual. Cada SlotType de la base de
        | datos es directamente un bloque del selector (Piloto, RTO, Médico y
        | Sanitario, etc.) y sus quickNames son los botones que aparecen debajo.
        |
        */
        SlotType::query()
            ->with([
                'quickNames' => fn ($query) => $query
                    ->orderBy('sort_order')
                    ->orderBy('name'),
            ])
            ->orderBy('picker_column')
            ->orderBy('picker_order')
            ->orderBy('name')
            ->get()
            ->each(
                function (SlotType $slotType) use (&$groups, &$metadata, &$columns): void {
                    $slotTypeName = $slotType->name;
                    $pickerColumn = max(
                        1,
                        min(4, (int) ($slotType->picker_column ?: 1))
                    );
                    $options = [];

                    if ($slotType->quickNames->isEmpty()) {
                        $choice = 'type:' . $slotType->id;

                        $options[$choice] = $slotTypeName;
                        $metadata[$choice] = [
                            'slot_type_name' => $slotTypeName,
                            'name' => $slotTypeName,
                        ];
                    } else {
                        foreach ($slotType->quickNames as $quickName) {
                            $choice = 'quick:' . $quickName->id;

                            $options[$choice] = $quickName->name;
                            $metadata[$choice] = [
                                'slot_type_name' => $slotTypeName,
                                'name' => $quickName->name,
                            ];
                        }
                    }

                    $groups[$slotTypeName] = $options;
                    $columns[$pickerColumn][$slotTypeName] = $options;
                }
            );

        self::$pickerGroupsCache = $groups;
        self::$choiceMetadataCache = $metadata;
        self::$pickerColumnsCache = $columns;
    }
}
