<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use UnitEnum;
use App\Support\PermissionCatalog;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Columns\IconColumn;

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

        foreach (PermissionCatalog::groups() as $group) {
            $checkboxes = [];

            foreach ($group['resources'] ?? [] as $resource => $definition) {
                $checkboxes[] = CheckboxList::make(
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
                ->schema($checkboxes)
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

                        return "{$granted} / ".count($knownPermissions);
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
        return array_map(
            fn (string $resource): string =>
                PermissionCatalog::fieldName($resource),
            array_keys(PermissionCatalog::resources())
        );
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

        $grantedActions = 0;

        foreach ($actions as $action) {
            if (in_array(
                "{$resource}.{$action}",
                $permissionNames,
                true
            )) {
                $grantedActions++;
            }
        }

        if ($grantedActions === 0) {
            return 'danger';
        }

        if ($grantedActions === count($actions)) {
            return 'success';
        }

        return 'warning';
    }

    public static function permissionCheckboxes(): array
    {
        $components = [];

        foreach (self::permissionResources() as $resource => $label) {
            $components[] = CheckboxList::make("permissions_{$resource}")
                ->label("Permisos de {$label}")
                ->options(self::permissionActions())
                ->columns(4)
                ->bulkToggleable();
        }

        return $components;
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
            $selectedActions = [];

            foreach ($definition['actions'] as $action) {
                $permissionName = "{$resource}.{$action}";

                if (in_array($permissionName, $permissionNames, true)) {
                    $selectedActions[] = $action;
                }
            }

            $state[
                PermissionCatalog::fieldName($resource)
            ] = $selectedActions;
        }

        return $state;
    }

    public static function syncRolePermissions(Role $role, array $data): void
    {
        $guard = $role->guard_name ?: 'web';

        self::ensureKnownPermissionsExist($guard);

        if ($role->name === 'admin') {
            $role->syncPermissions(
                Permission::where('guard_name', $guard)
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

        $permissionNames = array_values(array_unique($permissionNames));

        $role->syncPermissions($permissionNames);

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