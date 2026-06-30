<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected array $rolePermissionData = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return array_merge(
            $data,
            RoleResource::getPermissionFormStateForRole($this->record),
        );
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->rolePermissionData = $data;

        return RoleResource::removePermissionFieldsFromData($data);
    }

    protected function afterSave(): void
    {
        RoleResource::syncRolePermissions(
            role: $this->record,
            data: $this->rolePermissionData,
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}