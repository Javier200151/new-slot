<?php

namespace App\Filament\Resources\SqaGroups\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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

                Textarea::make('description')
                    ->label('Descripción')
                    ->rows(4)
                    ->columnSpanFull(),

                FileUpload::make('image')
                    ->label('Imagen')
                    ->image()
                    ->disk('public')
                    ->directory('sqa-groups')
                    ->visibility('public')
                    ->preserveFilenames()
                    ->columnSpanFull(),
            ]);
    }
}
