<?php

namespace App\Filament\Resources\SlotTypes\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class QuickNamesRelationManager extends RelationManager
{
    protected static string $relationship = 'quickNames';

    protected static ?string $title = 'Selecciones rápidas';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            /*
            | La columna category se conserva temporalmente por compatibilidad
            | con la migración ya ejecutada, pero deja de ser un dato editable.
            | El encabezado real del selector es siempre el SlotType propietario.
            */
            Hidden::make('category')
                ->dehydrateStateUsing(
                    fn (): string => (string) $this->getOwnerRecord()->name
                ),

            TextInput::make('name')
                ->label('Nombre rápido')
                ->helperText('Nombre clicable que aparecerá debajo de este tipo de slot y que se copiará al campo Nombre del ORBAT.')
                ->required()
                ->maxLength(255),

            TextInput::make('sort_order')
                ->label('Orden')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required(),

            Toggle::make('is_course_student')
                ->label('Es alumno de curso')
                ->helperText('Los usuarios inscritos en slots con esta selección se propondrán al entregar la metopa del curso.')
                ->default(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),

                IconColumn::make('is_course_student')
                    ->label('Alumno')
                    ->boolean(),

                TextColumn::make('sort_order')
                    ->label('Orden')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([
                CreateAction::make()
                    ->label('Añadir selección rápida'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
