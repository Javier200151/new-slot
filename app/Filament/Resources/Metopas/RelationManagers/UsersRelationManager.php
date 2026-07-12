<?php

namespace App\Filament\Resources\Metopas\RelationManagers;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;


class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $relatedResource = UserResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nick')
                    ->label('Nick')
                    ->searchable(),

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

                        Placeholder::make('already_assigned_warning')
                            ->label('Aviso')
                            ->content(function ($get) {
                                $userIds = $get('user_ids') ?? [];

                                $alreadyAssignedUsers = $this->getAlreadyAssignedUserNicks($userIds);

                                if (empty($alreadyAssignedUsers)) {
                                    return '';
                                }

                                return 'Los usuarios: ' . implode(', ', $alreadyAssignedUsers) . ' ya tienen esta metopa. Si continúas, se actualizará la fecha de asignación.';
                            })
                            ->visible(function ($get) {
                                $userIds = $get('user_ids') ?? [];

                                return ! empty($this->getAlreadyAssignedUserNicks($userIds));
                            }),

                        Checkbox::make('confirm_update_existing')
                            ->label('Sí, quiero actualizar también los usuarios que ya tienen esta metopa')
                            ->required(function ($get) {
                                $userIds = $get('user_ids') ?? [];

                                return ! empty($this->getAlreadyAssignedUserNicks($userIds));
                            })
                            ->visible(function ($get) {
                                $userIds = $get('user_ids') ?? [];

                                return ! empty($this->getAlreadyAssignedUserNicks($userIds));
                            }),
                    ])
                    ->modalSubmitAction(function ($action) {
                        return $action
                            ->label('Asignar usuarios')
                            ->disabled(fn (): bool => $this->assignUsersSubmitShouldBeDisabled());
                    })
                    ->action(function (array $data): void {
                        $metopa = $this->getOwnerRecord();

                        $alreadyAssignedUsers = $this->getAlreadyAssignedUserNicks($data['user_ids'] ?? []);

                        if (! empty($alreadyAssignedUsers) && empty($data['confirm_update_existing'])) {
                            Notification::make()
                                ->title('Confirmación necesaria')
                                ->body('Los usuarios: ' . implode(', ', $alreadyAssignedUsers) . ' ya tienen esta metopa. Marca la casilla de confirmación para actualizarla.')
                                ->danger()
                                ->send();

                            return;
                        }

                        foreach ($data['user_ids'] as $userId) {
                            $metopa->users()->syncWithoutDetaching([
                                $userId => [
                                    'assigned_at' => $data['assigned_at'],
                                    'created_by' => Auth::id(),
                                    'updated_by' => Auth::id(),
                                ],
                            ]);
                        }

                        Notification::make()
                            ->title('Metopa asignada correctamente')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Editar fecha')
                    ->form([
                        DateTimePicker::make('assigned_at')
                            ->label('Fecha y hora de asignación')
                            ->displayFormat('d/m/Y H:i')
                            ->seconds(false)
                            ->required(),
                    ])
                    ->using(function ($record, array $data) {
                        $this->getOwnerRecord()
                            ->users()
                            ->updateExistingPivot($record->id, [
                                'assigned_at' => $data['assigned_at'],
                                'updated_by' => Auth::id(),
                            ]);

                        return $record;
                    }),

                DetachAction::make()
                    ->label('Quitar'),
            ]);
    }
    private function getAlreadyAssignedUserNicks(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        return $this->getOwnerRecord()
            ->users()
            ->whereIn('users.id', $userIds)
            ->orderBy('nick')
            ->pluck('nick')
            ->toArray();
    }
    private function assignUsersSubmitShouldBeDisabled(): bool
    {
        $data = $this->mountedTableActionsData[0] ?? [];

        $userIds = $data['user_ids'] ?? [];
        $confirmed = (bool) ($data['confirm_update_existing'] ?? false);

        if (empty($userIds)) {
            return false;
        }

        $alreadyAssignedUsers = $this->getAlreadyAssignedUserNicks($userIds);

        return ! empty($alreadyAssignedUsers) && ! $confirmed;
    }
}