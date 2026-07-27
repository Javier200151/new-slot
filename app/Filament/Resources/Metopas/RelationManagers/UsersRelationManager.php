<?php

namespace App\Filament\Resources\Metopas\RelationManagers;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $relatedResource = UserResource::class;

    /**
     * Controla quién puede ver el Relation Manager.
     */
    public static function canViewForRecord(
        Model $ownerRecord,
        string $pageClass
    ): bool {
        return Auth::user()?->can('user-metopas.view') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nick')
                    ->label('Nick')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),

                TextColumn::make('pivot.assigned_at')
                    ->label('Fecha de asignación')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('assignUsers')
                    ->label('Asignar usuarios')
                    ->icon('heroicon-o-user-plus')
                    ->visible(
                        fn (): bool =>
                            $this->canCreateAssignments()
                            || $this->canUpdateAssignments()
                    )
                    ->form([
                        Select::make('user_ids')
                            ->label('Usuarios')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->options(
                                User::query()
                                    ->orderBy('nick')
                                    ->pluck('nick', 'id')
                            )
                            ->required(),

                        DateTimePicker::make('assigned_at')
                            ->label('Fecha y hora de asignación')
                            ->displayFormat('d/m/Y H:i')
                            ->seconds(false)
                            ->default(now())
                            ->required(),

                        /*
                         * Informa si el usuario selecciona registros para
                         * los que no posee permisos suficientes.
                         */
                        Placeholder::make('permission_warning')
                            ->label('Permisos insuficientes')
                            ->content(function ($get): string {
                                $userIds = $this->normalizeUserIds(
                                    $get('user_ids') ?? []
                                );

                                if ($userIds === []) {
                                    return '';
                                }

                                $existingUserIds =
                                    $this->getAlreadyAssignedUserIds($userIds);

                                $newUserIds = array_values(
                                    array_diff(
                                        $userIds,
                                        $existingUserIds
                                    )
                                );

                                $messages = [];

                                if (
                                    $newUserIds !== []
                                    && ! $this->canCreateAssignments()
                                ) {
                                    $messages[] =
                                        'No tienes permiso para crear '
                                        .'asignaciones nuevas.';
                                }

                                if (
                                    $existingUserIds !== []
                                    && ! $this->canUpdateAssignments()
                                ) {
                                    $messages[] =
                                        'No tienes permiso para actualizar '
                                        .'asignaciones existentes.';
                                }

                                return implode(' ', $messages);
                            })
                            ->visible(function ($get): bool {
                                $userIds = $this->normalizeUserIds(
                                    $get('user_ids') ?? []
                                );

                                if ($userIds === []) {
                                    return false;
                                }

                                $existingUserIds =
                                    $this->getAlreadyAssignedUserIds($userIds);

                                $newUserIds = array_values(
                                    array_diff(
                                        $userIds,
                                        $existingUserIds
                                    )
                                );

                                return (
                                    $newUserIds !== []
                                    && ! $this->canCreateAssignments()
                                ) || (
                                    $existingUserIds !== []
                                    && ! $this->canUpdateAssignments()
                                );
                            }),

                        Placeholder::make('already_assigned_warning')
                            ->label('Aviso')
                            ->content(function ($get): string {
                                $userIds = $this->normalizeUserIds(
                                    $get('user_ids') ?? []
                                );

                                $alreadyAssignedUsers =
                                    $this->getAlreadyAssignedUserNicks(
                                        $userIds
                                    );

                                if ($alreadyAssignedUsers === []) {
                                    return '';
                                }

                                return 'Los usuarios: '
                                    .implode(', ', $alreadyAssignedUsers)
                                    .' ya tienen esta metopa. '
                                    .'Si continúas, se actualizará la '
                                    .'fecha de asignación.';
                            })
                            ->visible(function ($get): bool {
                                $userIds = $this->normalizeUserIds(
                                    $get('user_ids') ?? []
                                );

                                return $this->canUpdateAssignments()
                                    && $this->getAlreadyAssignedUserNicks(
                                        $userIds
                                    ) !== [];
                            }),

                        Checkbox::make('confirm_update_existing')
                            ->label(
                                'Sí, quiero actualizar también los usuarios '
                                .'que ya tienen esta metopa'
                            )
                            ->required(function ($get): bool {
                                $userIds = $this->normalizeUserIds(
                                    $get('user_ids') ?? []
                                );

                                return $this->canUpdateAssignments()
                                    && $this->getAlreadyAssignedUserNicks(
                                        $userIds
                                    ) !== [];
                            })
                            ->visible(function ($get): bool {
                                $userIds = $this->normalizeUserIds(
                                    $get('user_ids') ?? []
                                );

                                return $this->canUpdateAssignments()
                                    && $this->getAlreadyAssignedUserNicks(
                                        $userIds
                                    ) !== [];
                            }),
                    ])
                    ->modalSubmitAction(function (Action $action): Action {
                        return $action
                            ->label('Guardar asignaciones')
                            ->disabled(
                                fn (): bool =>
                                    $this
                                        ->assignUsersSubmitShouldBeDisabled()
                            );
                    })
                    ->action(function (array $data): void {
                        $metopa = $this->getOwnerRecord();

                        $selectedUserIds = $this->normalizeUserIds(
                            $data['user_ids'] ?? []
                        );

                        $existingUserIds =
                            $this->getAlreadyAssignedUserIds(
                                $selectedUserIds
                            );

                        $newUserIds = array_values(
                            array_diff(
                                $selectedUserIds,
                                $existingUserIds
                            )
                        );

                        /*
                         * Comprobación real en servidor.
                         */
                        if ($newUserIds !== []) {
                            Gate::authorize('user-metopas.create');
                        }

                        if ($existingUserIds !== []) {
                            Gate::authorize('user-metopas.update');
                        }

                        $alreadyAssignedUsers =
                            $this->getAlreadyAssignedUserNicks(
                                $selectedUserIds
                            );

                        if (
                            $alreadyAssignedUsers !== []
                            && empty($data['confirm_update_existing'])
                        ) {
                            Notification::make()
                                ->title('Confirmación necesaria')
                                ->body(
                                    'Los usuarios: '
                                    .implode(
                                        ', ',
                                        $alreadyAssignedUsers
                                    )
                                    .' ya tienen esta metopa. '
                                    .'Marca la casilla de confirmación '
                                    .'para actualizarla.'
                                )
                                ->danger()
                                ->send();

                            return;
                        }

                        DB::transaction(function () use (
                            $metopa,
                            $newUserIds,
                            $existingUserIds,
                            $data
                        ): void {
                            /*
                             * Nuevas asignaciones.
                             *
                             * created_by solo se establece al crear.
                             */
                            foreach ($newUserIds as $userId) {
                                $metopa->users()->attach($userId, [
                                    'assigned_at' => $data['assigned_at'],
                                    'created_by' => Auth::id(),
                                    'updated_by' => Auth::id(),
                                ]);
                            }

                            /*
                             * Asignaciones existentes.
                             *
                             * No modificamos created_by.
                             */
                            foreach ($existingUserIds as $userId) {
                                $metopa
                                    ->users()
                                    ->updateExistingPivot($userId, [
                                        'assigned_at' =>
                                            $data['assigned_at'],
                                        'updated_by' => Auth::id(),
                                    ]);
                            }
                        });

                        Notification::make()
                            ->title(
                                'Asignaciones guardadas correctamente'
                            )
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                /*
                 * Editar exclusivamente la asignación.
                 *
                 * No utiliza users.update.
                 */
                Action::make('editAssignment')
                    ->label('Editar fecha')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(
                        fn (): bool =>
                            $this->canUpdateAssignments()
                    )
                    ->fillForm(
                        fn (User $record): array => [
                            'assigned_at' =>
                                $record->pivot?->assigned_at,
                        ]
                    )
                    ->form([
                        DateTimePicker::make('assigned_at')
                            ->label('Fecha y hora de asignación')
                            ->displayFormat('d/m/Y H:i')
                            ->seconds(false)
                            ->required(),
                    ])
                    ->action(
                        function (
                            User $record,
                            array $data
                        ): void {
                            Gate::authorize(
                                'user-metopas.update'
                            );

                            $this
                                ->getOwnerRecord()
                                ->users()
                                ->updateExistingPivot(
                                    $record->getKey(),
                                    [
                                        'assigned_at' =>
                                            $data['assigned_at'],
                                        'updated_by' => Auth::id(),
                                    ]
                                );

                            Notification::make()
                                ->title(
                                    'Fecha de asignación actualizada'
                                )
                                ->success()
                                ->send();
                        }
                    ),

                /*
                 * Quitar exclusivamente la asignación.
                 *
                 * No elimina al usuario.
                 */
                Action::make('removeAssignment')
                    ->label('Quitar')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Quitar metopa')
                    ->modalDescription(
                        'Se quitará esta metopa al usuario seleccionado.'
                    )
                    ->modalSubmitActionLabel('Quitar')
                    ->visible(
                        fn (): bool =>
                            $this->canDeleteAssignments()
                    )
                    ->action(function (User $record): void {
                        Gate::authorize(
                            'user-metopas.delete'
                        );

                        $this
                            ->getOwnerRecord()
                            ->users()
                            ->detach($record->getKey());

                        Notification::make()
                            ->title(
                                'Metopa retirada correctamente'
                            )
                            ->success()
                            ->send();
                    }),
            ]);
    }

    /**
     * Devuelve los ID seleccionados que ya tienen la metopa.
     *
     * @param array<int|string> $userIds
     * @return array<int>
     */
    private function getAlreadyAssignedUserIds(
        array $userIds
    ): array {
        $userIds = $this->normalizeUserIds($userIds);

        if ($userIds === []) {
            return [];
        }

        return $this
            ->getOwnerRecord()
            ->users()
            ->whereIn('users.id', $userIds)
            ->pluck('users.id')
            ->map(
                fn (mixed $userId): int => (int) $userId
            )
            ->values()
            ->all();
    }

    /**
     * Devuelve los nicks seleccionados que ya tienen la metopa.
     *
     * @param array<int|string> $userIds
     * @return array<string>
     */
    private function getAlreadyAssignedUserNicks(
        array $userIds
    ): array {
        $userIds = $this->normalizeUserIds($userIds);

        if ($userIds === []) {
            return [];
        }

        return $this
            ->getOwnerRecord()
            ->users()
            ->whereIn('users.id', $userIds)
            ->orderBy('nick')
            ->pluck('nick')
            ->all();
    }

    /**
     * Determina si el botón de guardar debe estar deshabilitado.
     */
    private function assignUsersSubmitShouldBeDisabled(): bool
    {
        $data = $this->mountedTableActionsData[0] ?? [];

        $userIds = $this->normalizeUserIds(
            $data['user_ids'] ?? []
        );

        if ($userIds === []) {
            return false;
        }

        $existingUserIds =
            $this->getAlreadyAssignedUserIds($userIds);

        $newUserIds = array_values(
            array_diff($userIds, $existingUserIds)
        );

        /*
         * Hay asignaciones nuevas, pero no tiene permiso create.
         */
        if (
            $newUserIds !== []
            && ! $this->canCreateAssignments()
        ) {
            return true;
        }

        /*
         * Hay asignaciones existentes, pero no tiene permiso update.
         */
        if (
            $existingUserIds !== []
            && ! $this->canUpdateAssignments()
        ) {
            return true;
        }

        /*
         * Para actualizar asignaciones existentes debe confirmar.
         */
        $confirmed = (bool) (
            $data['confirm_update_existing'] ?? false
        );

        return $existingUserIds !== [] && ! $confirmed;
    }

    /**
     * Normaliza los ID procedentes del Select.
     *
     * @param array<int|string> $userIds
     * @return array<int>
     */
    private function normalizeUserIds(array $userIds): array
    {
        return collect($userIds)
            ->filter(
                fn (mixed $userId): bool =>
                    is_numeric($userId)
            )
            ->map(
                fn (mixed $userId): int => (int) $userId
            )
            ->unique()
            ->values()
            ->all();
    }

    private function canCreateAssignments(): bool
    {
        return Auth::user()?->can(
            'user-metopas.create'
        ) ?? false;
    }

    private function canUpdateAssignments(): bool
    {
        return Auth::user()?->can(
            'user-metopas.update'
        ) ?? false;
    }

    private function canDeleteAssignments(): bool
    {
        return Auth::user()?->can(
            'user-metopas.delete'
        ) ?? false;
    }
}