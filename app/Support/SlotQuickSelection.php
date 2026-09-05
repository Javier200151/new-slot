<?php

namespace App\Support;

use App\Models\SlotType;
use App\Models\SlotTypeQuickName;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SlotQuickSelection
{
    private const PICKER_CACHE_KEY = 'newslot:slot-quick-selection:v3';
    private static ?array $pickerGroupsCache = null;

    private static ?array $choiceMetadataCache = null;

    private static ?array $pickerColumnsCache = null;

    private static ?array $pickerImagesCache = null;

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

    public static function pickerImages(): array
    {
        self::buildPickerCache();

        return self::$pickerImagesCache ?? [];
    }

    public static function clearCache(): void
    {
        self::$pickerGroupsCache = null;
        self::$choiceMetadataCache = null;
        self::$pickerColumnsCache = null;
        self::$pickerImagesCache = null;

        Cache::forget(self::PICKER_CACHE_KEY);
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
        self::buildPickerCache();

        $groups = $orbat['groups'] ?? [];
        $metadata = self::$choiceMetadataCache ?? [];
        $quickChoiceByTypeAndName = [];

        foreach ($metadata as $choice => $choiceMetadata) {
            $slotTypeId = (int) ($choiceMetadata['slot_type_id'] ?? 0);
            $quickNameId = (int) ($choiceMetadata['slot_quick_name_id'] ?? 0);

            if ($slotTypeId <= 0 || $quickNameId <= 0) {
                continue;
            }

            $quickChoiceByTypeAndName[$slotTypeId][self::normalizeName(
                (string) ($choiceMetadata['name'] ?? '')
            )] = $choice;
        }

        foreach ($groups as $groupIndex => $group) {
            foreach (($group['slots'] ?? []) as $slotIndex => $slot) {
                $slotTypeId = (int) ($slot['slot_type_id'] ?? 0);
                $quickNameId = (int) ($slot['slot_quick_name_id'] ?? 0);
                $choice = null;

                if ($quickNameId > 0) {
                    $quickChoice = 'quick:' . $quickNameId;

                    if (isset($metadata[$quickChoice])) {
                        $choice = $quickChoice;
                    }
                }

                if (! $choice && $slotTypeId > 0) {
                    $choice = $quickChoiceByTypeAndName[$slotTypeId][
                        self::normalizeName((string) ($slot['name'] ?? ''))
                    ] ?? null;

                    if (! $choice) {
                        $typeChoice = 'type:' . $slotTypeId;
                        $choice = isset($metadata[$typeChoice])
                            ? $typeChoice
                            : null;
                    }
                }

                $resolved = $choice ? ($metadata[$choice] ?? null) : null;

                $groups[$groupIndex]['slots'][$slotIndex]['slot_choice'] = $choice;
                $groups[$groupIndex]['slots'][$slotIndex]['slot_quick_name_id'] =
                    $resolved['slot_quick_name_id'] ?? null;
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

        $metadata = self::choiceMetadata($choice);

        if (! $metadata) {
            return [
                'slot_type_id' => null,
                'slot_quick_name_id' => null,
                'name' => null,
            ];
        }

        return [
            'slot_type_id' => $metadata['slot_type_id'] ?? null,
            'slot_quick_name_id' => $metadata['slot_quick_name_id'] ?? null,
            'name' => $metadata['name'] ?? null,
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

    private static function normalizeName(string $value): string
    {
        return Str::lower(
            Str::ascii(trim($value))
        );
    }

    private static function buildPickerCache(): void
    {
        if (
            self::$pickerGroupsCache !== null
            && self::$choiceMetadataCache !== null
            && self::$pickerColumnsCache !== null
            && self::$pickerImagesCache !== null
        ) {
            return;
        }

        $payload = Cache::remember(
            self::PICKER_CACHE_KEY,
            now()->addMinutes(10),
            static function (): array {
                $groups = [];
                $metadata = [];
                $images = [];
                $columns = [
                    1 => [],
                    2 => [],
                    3 => [],
                    4 => [],
                ];

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
                        function (SlotType $slotType) use (&$groups, &$metadata, &$columns, &$images): void {
                            $slotTypeName = $slotType->name;
                            $slotTypeId = (int) $slotType->id;
                            $images[$slotTypeName] = $slotType->image;
                            $pickerColumn = max(
                                1,
                                min(4, (int) ($slotType->picker_column ?: 1))
                            );
                            $options = [];
                            $typeChoice = 'type:' . $slotTypeId;

                            // El tipo se conserva siempre en metadatos para poder
                            // resolver ORBATs legacy sin consultar la BD slot a slot.
                            $metadata[$typeChoice] = [
                                'slot_type_id' => $slotTypeId,
                                'slot_quick_name_id' => null,
                                'slot_type_name' => $slotTypeName,
                                'name' => $slotTypeName,
                            ];

                            if ($slotType->quickNames->isEmpty()) {
                                $options[$typeChoice] = $slotTypeName;
                            } else {
                                foreach ($slotType->quickNames as $quickName) {
                                    $choice = 'quick:' . $quickName->id;

                                    $options[$choice] = $quickName->name;
                                    $metadata[$choice] = [
                                        'slot_type_id' => $slotTypeId,
                                        'slot_quick_name_id' => (int) $quickName->id,
                                        'slot_type_name' => $slotTypeName,
                                        'name' => $quickName->name,
                                    ];
                                }
                            }

                            $groups[$slotTypeName] = $options;
                            $columns[$pickerColumn][$slotTypeName] = $options;
                        }
                    );

                return [
                    'groups' => $groups,
                    'metadata' => $metadata,
                    'columns' => $columns,
                    'images' => $images,
                ];
            }
        );

        self::$pickerGroupsCache = $payload['groups'] ?? [];
        self::$choiceMetadataCache = $payload['metadata'] ?? [];
        self::$pickerColumnsCache = $payload['columns'] ?? [
            1 => [],
            2 => [],
            3 => [],
            4 => [],
        ];
        self::$pickerImagesCache = $payload['images'] ?? [];
    }

}
