<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Models\SqaGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserSqaGroupsRelationManager extends RelationManager
{
    protected static string $relationship = 'sqaGroupUsers';

    protected static ?string $title = 'Grupos SQA';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('sqa_group_id')
                    ->label('Grupo SQA')
                    ->options(fn ($record): array => SqaGroup::query()
                        ->where(function ($query) use ($record): void {
                            $assignedGroupIds = $this->getOwnerRecord()
                                ->sqaGroupUsers()
                                ->when($record, fn ($query) => $query->whereKeyNot($record->id))
                                ->pluck('sqa_group_id');

                            $query->whereNotIn('id', $assignedGroupIds);
                        })
                        ->orderBy('display_order')
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->required(),

                Toggle::make('main')
                    ->label('Principal')
                    ->inline(false)
                    ->default(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('sqaGroup.name')
            ->columns([
                TextColumn::make('sqaGroup.name')
                    ->label('Grupo')
                    ->searchable()
                    ->sortable(),

                ColorColumn::make('sqaGroup.color')
                    ->label('Color')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('sqaGroup.display_order')
                    ->label('Orden')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('main')
                    ->label('Principal')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('updatedBy.nick')
                    ->label('Actualizado por')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Asignado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at')
            ->headerActions([
                CreateAction::make()
                    ->label('Añadir grupo'),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Editar'),

                DeleteAction::make()
                    ->label('Quitar'),
            ]);
    }
}
