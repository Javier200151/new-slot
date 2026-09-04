<?php

namespace App\Filament\Resources\SqaGroups\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Checkbox;
use Filament\Schemas\Schema;

class SqaGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),

                TextInput::make('large_name')
                    ->label('Nombre largo')
                    ->maxLength(255),

                ColorPicker::make('color')
                    ->label('Color'),

                TextInput::make('display_order')
                    ->label('Orden')
                    ->numeric()
                    ->minValue(0),

                Checkbox::make('show_in_organization')
                    ->label('Mostrar en el organigrama')
                    ->helperText('Si se desactiva, el grupo seguirá existiendo y mantendrá sus miembros, pero no aparecerá en /comunidad/organigrama.')
                    ->default(true),

                Checkbox::make('has_coordinator_role')
                    ->label('El grupo tiene figura de coordinador')
                    ->helperText('Si se desactiva, el organigrama no mostrará ningún puesto de coordinador para este grupo. Úsalo en grupos donde las decisiones se toman de forma colegiada.')
                    ->default(true),

                Textarea::make('description')
                    ->label('Descripción')
                    ->rows(4)
                    ->columnSpanFull(),

                FileUpload::make('image')
                    ->label('Imagen general')
                    ->helperText('Imagen del grupo para otros usos. El organigrama utiliza el icono específico de abajo.')
                    ->image()
                    ->disk('public')
                    ->directory('sqa-groups')
                    ->visibility('public')
                    ->preserveFilenames()
                    ->columnSpanFull(),

                FileUpload::make('icon')
                    ->label('Icono del organigrama')
                    ->helperText('Icono cuadrado o transparente que se mostrará junto al nombre del grupo en el organigrama.')
                    ->image()
                    ->disk('public')
                    ->directory('sqa-groups/icons')
                    ->visibility('public')
                    ->preserveFilenames()
                    ->columnSpanFull(),
            ]);
    }
}
