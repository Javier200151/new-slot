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
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
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

                Section::make('Buscar permisos')
                    ->description('Filtra los checks por bloque, permiso, acción o tipo de actividad.')
                    ->schema([
                        TextInput::make('permission_search')
                            ->label('Buscar permisos')
                            ->placeholder('Ej.: usuarios, eliminar, ORBAT, operación...')
                            ->prefixIcon('heroicon-o-magnifying-glass')
                            ->live(debounce: 200)
                            ->dehydrated(false)
                            ->extraInputAttributes([
                                'autocomplete' => 'off',
                            ]),
                    ])
                    ->columnSpanFull(),

                self::permissionTabs(),
            ]);
    }

    public static function permissionTabs(): Tabs
    {
        $tabs = [];
        $activityTypes = ActivityType::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        foreach (PermissionCatalog::groups() as $group) {
            $components = [];
            $baseGroupSearchTerms = [
                $group['label'] ?? '',
            ];
            $groupSearchTerms = $baseGroupSearchTerms;

            foreach ($group['resources'] ?? [] as $resource => $definition) {
                $resourceLabel = $definition['label'] ?? $resource;
                $actionOptions = PermissionCatalog::actionOptionsFor($resource);
                $resourceSearchTerms = array_merge(
                    $baseGroupSearchTerms,
                    [
                        $resource,
                        $resourceLabel,
                    ],
                    array_keys($actionOptions),
                    array_values($actionOptions),
                );

                if (PermissionCatalog::isActivityTypeScoped($resource)) {
                    $typeCheckboxes = [];
                    $sectionSearchTerms = $resourceSearchTerms;

                    foreach ($activityTypes as $activityType) {
                        $typeSearchTerms = array_merge(
                            $resourceSearchTerms,
                            [
                                (string) $activityType->id,
                                $activityType->name,
                            ],
                        );

                        $sectionSearchTerms = array_merge(
                            $sectionSearchTerms,
                            $typeSearchTerms,
                        );

                        $typeCheckboxes[] = CheckboxList::make(
                            PermissionCatalog::activityTypeFieldName(
                                $resource,
                                (int) $activityType->id,
                            )
                        )
                            ->label($activityType->name)
                            ->options($actionOptions)
                            ->columns(4)
                            ->bulkToggleable()
                            ->hidden(
                                fn (Get $get): bool => ! self::permissionSearchMatches(
                                    $get('permission_search'),
                                    $typeSearchTerms,
                                )
                            )
                            ->dehydratedWhenHidden();
                    }

                    $components[] = Section::make(
                        $resourceLabel . ' por tipo'
                    )
                        ->description(
                            'Selecciona qué acciones puede realizar este rol en cada tipo.'
                        )
                        ->schema($typeCheckboxes)
                        ->columns(2)
                        ->columnSpanFull()
                        ->hidden(
                            fn (Get $get): bool => ! self::permissionSearchMatches(
                                $get('permission_search'),
                                $sectionSearchTerms,
                            )
                        );

                    $groupSearchTerms = array_merge(
                        $groupSearchTerms,
                        $sectionSearchTerms,
                    );

                    continue;
                }

                $components[] = CheckboxList::make(
                    PermissionCatalog::fieldName($resource)
                )
                    ->label($resourceLabel)
                    ->options($actionOptions)
                    ->columns(4)
                    ->bulkToggleable()
                    ->hidden(
                        fn (Get $get): bool => ! self::permissionSearchMatches(
                            $get('permission_search'),
                            $resourceSearchTerms,
                        )
                    )
                    ->dehydratedWhenHidden();

                $groupSearchTerms = array_merge(
                    $groupSearchTerms,
                    $resourceSearchTerms,
                );
            }

            $tabs[] = Tab::make($group['label'])
                ->icon($group['icon'] ?? null)
                ->schema($components)
                ->columns(2)
                ->hidden(
                    fn (Get $get): bool => ! self::permissionSearchMatches(
                        $get('permission_search'),
                        $groupSearchTerms,
                    )
                );
        }

        return Tabs::make('Permisos')
            ->tabs($tabs)
            ->persistTab()
            ->id('role-permission-tabs')
            ->columnSpanFull();
    }

    /**
     * El buscador solo afecta a la presentación. Los CheckboxList ocultos
     * continúan deshidratando su estado para no perder permisos al guardar.
     */
    public static function permissionSearchMatches(
        mixed $search,
        array $terms,
    ): bool {
        $search = trim((string) $search);

        if ($search === '') {
            return true;
        }

        $search = Str::lower(Str::ascii($search));
        $tokens = preg_split('/\s+/u', $search) ?: [];
        $haystack = Str::lower(Str::ascii(
            implode(' ', array_filter(array_map(
                static fn ($term): string => (string) $term,
                $terms,
            )))
        ));

        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }

            if (! str_contains($haystack, $token)) {
                return false;
            }
        }

        return true;
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
            ->searchable()
            ->searchPlaceholder('Buscar roles…')
            ->persistSearchInSession()
            ->actions([
                EditAction::make(),
                self::duplicateAction(),
                DeleteAction::make(),
            ]);
    }

    public static function duplicateAction(): ReplicateAction
    {
        return ReplicateAction::make('duplicate')
            ->label('Duplicar')
            ->icon('heroicon-o-square-2-stack')
            ->color('warning')
            ->authorize('create', Role::class)
            ->requiresConfirmation()
            ->modalHeading('Duplicar rol')
            ->modalDescription(
                'Se copiarán todos los permisos del rol. Los usuarios asignados no se duplicarán.'
            )
            ->beforeReplicaSaved(function (Role $record, Role $replica): void {
                $replica->name = self::nextDuplicateName($record);
            })
            ->after(function (Role $record, Role $replica): void {
                $record->loadMissing('permissions');

                $replica->syncPermissions(
                    $record->permissions
                        ->pluck('name')
                        ->all()
                );

                app(PermissionRegistrar::class)
                    ->forgetCachedPermissions();
            })
            ->successNotificationTitle(
                fn (Role $replica): string =>
                    "Rol {$replica->name} duplicado correctamente."
            )
            ->successRedirectUrl(
                fn (Role $replica): string => self::getUrl(
                    'edit',
                    ['record' => $replica],
                )
            );
    }

    public static function nextDuplicateName(Role $role): string
    {
        $baseName = $role->name . '_duplicado';
        $candidate = $baseName;
        $suffix = 2;

        while (
            Role::query()
                ->where('guard_name', $role->guard_name ?: 'web')
                ->where('name', $candidate)
                ->exists()
        ) {
            $candidate = $baseName . '_' . $suffix;
            $suffix++;
        }

        return $candidate;
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
        $activityTypeIds = PermissionCatalog::activityTypeIds();

        foreach (PermissionCatalog::resources() as $resource => $definition) {
            if (PermissionCatalog::isActivityTypeScoped($resource)) {
                foreach ($activityTypeIds as $activityTypeId) {
                    $fields[] = PermissionCatalog::activityTypeFieldName(
                        $resource,
                        $activityTypeId,
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
            foreach (PermissionCatalog::activityTypeIds() as $activityTypeId) {
                foreach ($actions as $action) {
                    $expected[] = PermissionCatalog::activityTypePermissionName(
                        $resource,
                        $activityTypeId,
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
                foreach (PermissionCatalog::activityTypeIds() as $activityTypeId) {
                    $selectedActions = [];

                    foreach ($definition['actions'] as $action) {
                        $permissionName =
                            PermissionCatalog::activityTypePermissionName(
                                $resource,
                                $activityTypeId,
                                $action,
                            );

                        if (in_array($permissionName, $permissionNames, true)) {
                            $selectedActions[] = $action;
                        }
                    }

                    $state[
                        PermissionCatalog::activityTypeFieldName(
                            $resource,
                            $activityTypeId,
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
                foreach (PermissionCatalog::activityTypeIds() as $activityTypeId) {
                    $field = PermissionCatalog::activityTypeFieldName(
                        $resource,
                        $activityTypeId,
                    );

                    foreach ($data[$field] ?? [] as $action) {
                        if (! in_array($action, $definition['actions'], true)) {
                            continue;
                        }

                        $permissionNames[] =
                            PermissionCatalog::activityTypePermissionName(
                                $resource,
                                $activityTypeId,
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
