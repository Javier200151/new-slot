<?php

namespace App\Filament\Resources\SqaGroups\RelationManagers;

use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class SqaGroupUsersRelationManager extends RelationManager
{
    protected static string $relationship = 'sqaGroupUsers';

    protected static ?string $title = 'Usuarios del grupo';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Usuario')
                    ->options(fn ($record): array => User::query()
                        ->where(function ($query) use ($record): void {
                            $assignedUserIds = $this->getOwnerRecord()
                                ->sqaGroupUsers()
                                ->when($record, fn ($query) => $query->whereKeyNot($record->id))
                                ->pluck('user_id');

                            $query->whereNotIn('id', $assignedUserIds);
                        })
                        ->orderBy('nick')
                        ->pluck('nick', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->required(),

                Toggle::make('main')
                    ->label('Principal')
                    ->inline(false)
                    ->default(false),

                Toggle::make('coordinator')
                    ->label('Coordinador del grupo')
                    ->helperText('Solo puede existir un coordinador por grupo. Activarlo aquí desmarca automáticamente al anterior.')
                    ->inline(false)
                    ->default(false)
                    ->visible(fn (): bool => (bool) $this->getOwnerRecord()->has_coordinator_role),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('user.nick')
            ->columns([
                TextColumn::make('user.nick')
                    ->label('Nick')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('main')
                    ->label('Principal')
                    ->boolean()
                    ->sortable(),

                ToggleColumn::make('coordinator')
                    ->label('Coordinador')
                    ->onColor('warning')
                    ->offColor('gray')
                    ->visible(fn (): bool => (bool) $this->getOwnerRecord()->has_coordinator_role)
                    ->disabled(fn (): bool => ! auth()->user()?->can('update', $this->getOwnerRecord())),

                TextColumn::make('updatedBy.nick')
                    ->label('Actualizado por')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Asignado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at')
            ->headerActions([
                CreateAction::make()
                    ->label('Asignar usuario'),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Editar'),

                DeleteAction::make()
                    ->label('Quitar'),
            ]);
    }
}
