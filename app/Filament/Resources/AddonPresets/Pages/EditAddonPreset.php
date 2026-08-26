<?php

namespace App\Filament\Resources\AddonPresets\Pages;

use App\Filament\Resources\AddonPresets\AddonPresetResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use App\Services\AuditLogger;

class EditAddonPreset extends EditRecord
{
    protected array $auditAddonsBefore = [];

    protected static string $resource = AddonPresetResource::class;

    protected function beforeSave(): void
    {
        $this->auditAddonsBefore =
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
    }


    protected function afterSave(): void
    {
        $addonsAfter =
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

        if (
            $this->auditAddonsBefore
            === $addonsAfter
        ) {
            return;
        }

        app(AuditLogger::class)
            ->change(
                subject: $this->record,

                event:
                    'addon_preset_addons_updated',

                old: [
                    'addons' =>
                        $this->auditAddonsBefore,
                ],

                new: [
                    'addons' =>
                        $addonsAfter,
                ],

                properties: [
                    'relation' => 'addons',

                    'table' =>
                        'addon_preset_addon',
                ],
            );
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
