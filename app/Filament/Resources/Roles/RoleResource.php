<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Models\ActivityType;
use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\PermissionRegistrar;
use UnitEnum;

class RoleResource extends Resource
{
    protected static string | UnitEnum | null $navigationGroup = 'Usuarios';
    protected static ?int $navigationSort = 4;

    protected static ?string $model = Role::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Roles';

    protected static ?string $modelLabel = 'Rol';

    protected static ?string $pluralModelLabel = 'Roles';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos del rol')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre del rol')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->disabled(
                                fn (?Role $record): bool =>
                                    $record?->name === 'admin'
                            ),

                        TextInput::make('guard_name')
                            ->default('web')
                            ->required()
                            ->hidden(),

                        Toggle::make('can_access_filament')
                            ->label('Puede acceder al panel Filament')
                            ->helperText(
                                'Los usuarios con este rol podrán entrar al panel.'
                            ),
                    ])
                    ->columns(2),

                self::permissionTabs(),
            ]);
    }

    public static function permissionTabs(): Tabs
    {
        $tabs = [];
        $operationTypes = ActivityType::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        foreach (PermissionCatalog::groups() as $group) {
            $components = [];

            foreach ($group['resources'] ?? [] as $resource => $definition) {
                if (PermissionCatalog::isActivityTypeScoped($resource)) {
                    $typeCheckboxes = [];

                    foreach ($operationTypes as $operationType) {
                        $typeCheckboxes[] = CheckboxList::make(
                            PermissionCatalog::activityTypeFieldName(
                                $resource,
                                (int) $operationType->id,
                            )
                        )
                            ->label($operationType->name)
                            ->options(
                                PermissionCatalog::actionOptionsFor($resource)
                            )
                            ->columns(4)
                            ->bulkToggleable();
                    }

                    $components[] = Section::make(
                        ($definition['label'] ?? $resource) . ' por tipo'
                    )
                        ->description(
                            'Selecciona qué acciones puede realizar este rol en cada tipo.'
                        )
                        ->schema($typeCheckboxes)
                        ->columns(2)
                        ->columnSpanFull();

                    continue;
                }

                $components[] = CheckboxList::make(
                    PermissionCatalog::fieldName($resource)
                )
                    ->label($definition['label'] ?? $resource)
                    ->options(
                        PermissionCatalog::actionOptionsFor($resource)
                    )
                    ->columns(4)
                    ->bulkToggleable();
            }

            $tabs[] = Tab::make($group['label'])
                ->icon($group['icon'] ?? null)
                ->schema($components)
                ->columns(2);
        }

        return Tabs::make('Permisos')
            ->tabs($tabs)
            ->persistTab()
            ->id('role-permission-tabs')
            ->columnSpanFull();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Rol')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('filament_access')
                    ->label('Acceso a Filament')
                    ->boolean()
                    ->state(function (Role $record): bool {
                        $record->loadMissing('permissions');

                        return $record->name === 'admin'
                            || $record->permissions
                                ->contains('name', 'filament.access');
                    }),

                TextColumn::make('permission_total')
                    ->label('Permisos')
                    ->badge()
                    ->state(function (Role $record): string {
                        $record->loadMissing('permissions');

                        $knownPermissions = PermissionCatalog::permissionNames();

                        $granted = $record->permissions
                            ->pluck('name')
                            ->intersect($knownPermissions)
                            ->count();

                        return "{$granted} / " . count($knownPermissions);
                    })
                    ->color(function (Role $record): string {
                        $record->loadMissing('permissions');

                        $knownPermissions = PermissionCatalog::permissionNames();

                        $granted = $record->permissions
                            ->pluck('name')
                            ->intersect($knownPermissions)
                            ->count();

                        if ($granted === 0) {
                            return 'danger';
                        }

                        if ($granted === count($knownPermissions)) {
                            return 'success';
                        }

                        return 'warning';
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }

    public static function permissionResources(): array
    {
        $result = [];

        foreach (PermissionCatalog::resources() as $resource => $definition) {
            $result[$resource] = $definition['label'];
        }

        return $result;
    }

    public static function permissionActions(): array
    {
        return PermissionCatalog::actions();
    }

    public static function permissionFieldNames(): array
    {
        $fields = [];
        $operationTypeIds = PermissionCatalog::activityTypeIds();

        foreach (PermissionCatalog::resources() as $resource => $definition) {
            if (PermissionCatalog::isActivityTypeScoped($resource)) {
                foreach ($operationTypeIds as $operationTypeId) {
                    $fields[] = PermissionCatalog::activityTypeFieldName(
                        $resource,
                        $operationTypeId,
                    );
                }

                continue;
            }

            $fields[] = PermissionCatalog::fieldName($resource);
        }

        return $fields;
    }

    public static function getPermissionBadgeColor(
        Role $role,
        string $resource
    ): string {
        $role->loadMissing('permissions');

        $actions = PermissionCatalog::actionsFor($resource);
        $permissionNames = $role->permissions
            ->pluck('name')
            ->all();

        $expected = [];

        if (PermissionCatalog::isActivityTypeScoped($resource)) {
            foreach (PermissionCatalog::activityTypeIds() as $operationTypeId) {
                foreach ($actions as $action) {
                    $expected[] = PermissionCatalog::activityTypePermissionName(
                        $resource,
                        $operationTypeId,
                        $action,
                    );
                }
            }
        } else {
            foreach ($actions as $action) {
                $expected[] = "{$resource}.{$action}";
            }
        }

        $granted = count(array_intersect($expected, $permissionNames));

        if ($granted === 0) {
            return 'danger';
        }

        if ($granted === count($expected)) {
            return 'success';
        }

        return 'warning';
    }

    public static function removePermissionFieldsFromData(array $data): array
    {
        unset($data['can_access_filament']);

        foreach (self::permissionFieldNames() as $field) {
            unset($data[$field]);
        }

        return $data;
    }

    public static function getPermissionFormStateForRole(Role $role): array
    {
        $role->loadMissing('permissions');

        $permissionNames = $role->permissions
            ->pluck('name')
            ->toArray();

        $state = [
            'can_access_filament' => $role->name === 'admin'
                || in_array('filament.access', $permissionNames, true),
        ];

        foreach (
            PermissionCatalog::resources()
            as $resource => $definition
        ) {
            if (PermissionCatalog::isActivityTypeScoped($resource)) {
                foreach (PermissionCatalog::activityTypeIds() as $operationTypeId) {
                    $selectedActions = [];

                    foreach ($definition['actions'] as $action) {
                        $permissionName =
                            PermissionCatalog::activityTypePermissionName(
                                $resource,
                                $operationTypeId,
                                $action,
                            );

                        if (in_array($permissionName, $permissionNames, true)) {
                            $selectedActions[] = $action;
                        }
                    }

                    $state[
                        PermissionCatalog::activityTypeFieldName(
                            $resource,
                            $operationTypeId,
                        )
                    ] = $selectedActions;
                }

                continue;
            }

            $selectedActions = [];

            foreach ($definition['actions'] as $action) {
                $permissionName = "{$resource}.{$action}";

                if (in_array($permissionName, $permissionNames, true)) {
                    $selectedActions[] = $action;
                }
            }

            $state[PermissionCatalog::fieldName($resource)] = $selectedActions;
        }

        return $state;
    }

    public static function syncRolePermissions(Role $role, array $data): void
    {
        $guard = $role->guard_name ?: 'web';

        self::ensureKnownPermissionsExist($guard);

        $knownPermissions = PermissionCatalog::permissionNames();

        if ($role->name === 'admin') {
            $role->syncPermissions(
                Permission::query()
                    ->where('guard_name', $guard)
                    ->whereIn('name', $knownPermissions)
                    ->pluck('name')
                    ->toArray()
            );

            app()[PermissionRegistrar::class]->forgetCachedPermissions();

            return;
        }

        $permissionNames = [];

        if ((bool) ($data['can_access_filament'] ?? false)) {
            $permissionNames[] = 'filament.access';
        }

        foreach (
            PermissionCatalog::resources()
            as $resource => $definition
        ) {
            if (PermissionCatalog::isActivityTypeScoped($resource)) {
                foreach (PermissionCatalog::activityTypeIds() as $operationTypeId) {
                    $field = PermissionCatalog::activityTypeFieldName(
                        $resource,
                        $operationTypeId,
                    );

                    foreach ($data[$field] ?? [] as $action) {
                        if (! in_array($action, $definition['actions'], true)) {
                            continue;
                        }

                        $permissionNames[] =
                            PermissionCatalog::activityTypePermissionName(
                                $resource,
                                $operationTypeId,
                                $action,
                            );
                    }
                }

                continue;
            }

            $field = PermissionCatalog::fieldName($resource);
            $selectedActions = $data[$field] ?? [];

            foreach ($selectedActions as $action) {
                if (! in_array(
                    $action,
                    $definition['actions'],
                    true
                )) {
                    continue;
                }

                $permissionNames[] = "{$resource}.{$action}";
            }
        }

        $role->syncPermissions(array_values(array_unique($permissionNames)));

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public static function ensureKnownPermissionsExist(
        string $guard = 'web'
    ): void {
        foreach (
            PermissionCatalog::permissionNames()
            as $permissionName
        ) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => $guard,
            ]);
        }

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }
}
