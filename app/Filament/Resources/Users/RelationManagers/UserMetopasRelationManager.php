<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Models\Metopa;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserMetopasRelationManager extends RelationManager
{
    protected static string $relationship = 'userMetopas';

    protected static ?string $title = 'Metopas';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('metopa_id')
                    ->label('Metopa')
                    ->options(Metopa::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),

                DateTimePicker::make('assigned_at')
                    ->label('Fecha de asignación')
                    ->seconds(false)
                    ->default(now())
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('metopa.name')
            ->columns([
                ImageColumn::make('metopa_image')
                    ->label('Imagen')
                    ->getStateUsing(fn ($record) => $record->metopa?->image
                        ? asset('storage/' . $record->metopa->image)
                        : null
                    )
                    ->size(40),

                TextColumn::make('metopa.name')
                    ->label('Metopa')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('assigned_at')
                    ->label('Fecha de asignación')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('assigned_at', 'asc')
            ->headerActions([
                CreateAction::make()
                    ->label('Añadir metopa'),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Editar fecha'),

                DeleteAction::make()
                    ->label('Quitar'),
            ]);
    }
}