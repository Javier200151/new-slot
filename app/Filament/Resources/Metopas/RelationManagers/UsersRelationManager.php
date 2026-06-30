<?php

namespace App\Filament\Resources\Metopas\RelationManagers;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
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
                    ->sortable()
                    ->dateTime('d/m/Y'),
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

                        DatePicker::make('assigned_at')
                            ->label('Fecha de asignación')
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
                        DatePicker::make('assigned_at')
                            ->label('Fecha de asignación')
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