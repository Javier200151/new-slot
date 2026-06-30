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
                    ])
                    ->action(function (array $data): void {
                        $metopa = $this->getOwnerRecord();

                        foreach ($data['user_ids'] as $userId) {
                            $metopa->users()->syncWithoutDetaching([
                                $userId => [
                                    'assigned_at' => $data['assigned_at'],
                                    'created_by' => Auth::id(),
                                    'updated_by' => Auth::id(),
                                ],
                            ]);
                        }
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
}