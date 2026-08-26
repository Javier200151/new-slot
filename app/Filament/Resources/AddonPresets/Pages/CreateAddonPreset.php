<?php

namespace App\Filament\Resources\AddonPresets\Pages;

use App\Filament\Resources\AddonPresets\AddonPresetResource;
use Filament\Resources\Pages\CreateRecord;
use App\Services\AuditLogger;

class CreateAddonPreset extends CreateRecord
{
    protected static string $resource = AddonPresetResource::class;

    protected function afterCreate(): void
    {
        $addons =
            $this->record
                ->addons()
                ->orderBy('name')
                ->get()
                ->map(
                    fn ($addon): array => [
                        'id' => $addon->id,
                        'name' => $addon->name,
                    ]
                )
                ->values()
                ->all();

        if ($addons === []) {
            return;
        }

        app(AuditLogger::class)
            ->change(
                subject: $this->record,

                event:
                    'addon_preset_addons_updated',

                old: [
                    'addons' => [],
                ],

                new: [
                    'addons' => $addons,
                ],

                properties: [
                    'relation' => 'addons',

                    'table' =>
                        'addon_preset_addon',

                    'action' =>
                        'initial_assignment',
                ],
            );
    }
}
