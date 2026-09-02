<?php

namespace App\Filament\Resources\ForumCategories\Schemas;

use App\Models\Status;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ForumCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Categoría')
                    ->description('El identificador interno se genera automáticamente al crearla y no cambia aunque después edites el título.')
                    ->schema([
                        TextInput::make('title')
                            ->label('Título')
                            ->required()
                            ->maxLength(120),

                        TextInput::make('singular')
                            ->label('Nombre en singular')
                            ->placeholder('Hilo')
                            ->helperText('Se usa en textos como “Nuevo debate” o “Nueva presentación”.')
                            ->maxLength(80),

                        TextInput::make('icon')
                            ->label('Icono')
                            ->helperText('Puedes usar un emoji, por ejemplo 👋, 💬 o 🥃.')
                            ->default('💬')
                            ->maxLength(32),

                        ColorPicker::make('color')
                            ->label('Color')
                            ->default('#38bdf8'),

                        Textarea::make('description')
                            ->label('Descripción')
                            ->rows(4)
                            ->maxLength(2000)
                            ->columnSpanFull(),

                        TextInput::make('hint')
                            ->label('Texto de ayuda al publicar')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Visibilidad y orden')
                    ->schema([
                        Select::make('statuses')
                            ->label('Estados de usuario que pueden verla')
                            ->relationship('statuses', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(
                                fn (): array => Status::query()
                                    ->whereIn('name', ['ACTIVO', 'RESERVA', 'RECLUTA'])
                                    ->pluck('id')
                                    ->all()
                            )
                            ->helperText('Admin y los roles con permisos de moderación de esta categoría podrán entrar aunque su estado no esté seleccionado.')
                            ->columnSpanFull(),

                        TextInput::make('sort_order')
                            ->label('Orden')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(65535)
                            ->default(100)
                            ->required(),

                        Toggle::make('is_enabled')
                            ->label('Categoría activa')
                            ->helperText('Si se desactiva, desaparece del foro público sin borrar sus hilos.')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Categoría interna')
                    ->description('Las categorías originales pueden editarse y cambiar su visibilidad, pero no eliminarse ni cambiar su función interna.')
                    ->schema([
                        TextInput::make('slug')
                            ->label('Identificador')
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('system_type')
                            ->label('Función interna')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(2)
                    ->visible(fn ($record): bool => (bool) $record?->is_system),
            ]);
    }
}
