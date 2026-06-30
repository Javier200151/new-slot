<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected array $rolePermissionData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->rolePermissionData = $data;

        return RoleResource::removePermissionFieldsFromData($data);
    }

    protected function afterCreate(): void
    {
        RoleResource::syncRolePermissions(
            role: $this->record,
            data: $this->rolePermissionData,
        );
    }
}