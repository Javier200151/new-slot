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

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Roles';

    protected static ?string $modelLabel = 'Rol';

    protected static ?string $pluralModelLabel = 'Roles';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components(array_merge([
                TextInput::make('name')
                    ->label('Nombre del rol')
                    ->required()
                    ->unique(ignoreRecord: true),

                TextInput::make('guard_name')
                    ->default('web')
                    ->required()
                    ->hidden(),

                Toggle::make('can_access_filament')
                    ->label('Puede acceder al panel Filament')
                    ->helperText('Si está activado, los usuarios con este rol podrán entrar al panel.'),
            ], self::permissionCheckboxes()));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Rol')
                    ->searchable(),

                TextColumn::make('permission_summary')
                    ->label('Permisos')
                    ->badge()
                    ->state(fn (Role $record): array => array_keys(self::permissionResources()))
                    ->formatStateUsing(fn (string $state): string => self::permissionResources()[$state] ?? $state)
                    ->color(fn (string $state, Role $record): string => self::getPermissionBadgeColor($record, $state)),
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
        return [
            'users' => 'Usuarios',
            'metopas' => 'Metopas',
            'promos' => 'Promos',
            'statuses' => 'Estados',
            'roles' => 'Roles',
            'permissions' => 'Permisos',
        ];
    }

    public static function permissionActions(): array
    {
        return [
            'view' => 'Ver',
            'create' => 'Crear',
            'update' => 'Modificar',
            'delete' => 'Eliminar',
        ];
    }
    public static function getPermissionBadgeColor(Role $role, string $resource): string
    {
        $role->loadMissing('permissions');

        $actions = array_keys(self::permissionActions());

        $permissionNames = $role->permissions
            ->pluck('name')
            ->toArray();

        $totalActions = count($actions);

        $grantedActions = 0;

        foreach ($actions as $action) {
            if (in_array("{$resource}.{$action}", $permissionNames, true)) {
                $grantedActions++;
            }
        }

        if ($grantedActions === 0) {
            return 'danger'; // rojo
        }

        if ($grantedActions === $totalActions) {
            return 'success'; // verde
        }

        return 'warning'; // naranja
    }
    public static function permissionCheckboxes(): array
    {
        $components = [];

        foreach (self::permissionResources() as $resource => $label) {
            $components[] = CheckboxList::make("permissions_{$resource}")
                ->label("Gestión de {$label}")
                ->options(self::permissionActions())
                ->columns(4)
                ->bulkToggleable();
        }

        return $components;
    }

    public static function permissionFieldNames(): array
    {
        return array_map(
            fn (string $resource): string => "permissions_{$resource}",
            array_keys(self::permissionResources())
        );
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

        foreach (self::permissionResources() as $resource => $label) {
            $selectedActions = [];

            foreach (array_keys(self::permissionActions()) as $action) {
                $permissionName = "{$resource}.{$action}";

                if (in_array($permissionName, $permissionNames, true)) {
                    $selectedActions[] = $action;
                }
            }

            $state["permissions_{$resource}"] = $selectedActions;
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

        foreach (self::permissionResources() as $resource => $label) {
            $field = "permissions_{$resource}";
            $actions = $data[$field] ?? [];

            foreach ($actions as $action) {
                if (! array_key_exists($action, self::permissionActions())) {
                    continue;
                }

                $permissionNames[] = "{$resource}.{$action}";
            }
        }

        $permissionNames = array_values(array_unique($permissionNames));

        $role->syncPermissions($permissionNames);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public static function ensureKnownPermissionsExist(string $guard = 'web'): void
    {
        Permission::firstOrCreate([
            'name' => 'filament.access',
            'guard_name' => $guard,
        ]);

        foreach (self::permissionResources() as $resource => $label) {
            foreach (array_keys(self::permissionActions()) as $action) {
                Permission::firstOrCreate([
                    'name' => "{$resource}.{$action}",
                    'guard_name' => $guard,
                ]);
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}