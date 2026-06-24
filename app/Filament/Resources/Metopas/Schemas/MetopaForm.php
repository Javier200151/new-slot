<?php

namespace App\Filament\Resources\Metopas\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MetopaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),

                Textarea::make('description')
                    ->label('Descripción')
                    ->nullable()
                    ->columnSpanFull(),

                FileUpload::make('image')
                    ->label('Imagen del banderín')
                    ->image()
                    ->disk('public')
                    ->directory('metopas')
                    ->visibility('public')
                    ->required(),

                FileUpload::make('image_large')
                    ->label('Imagen grande')
                    ->image()
                    ->disk('public')
                    ->directory('metopas/large')
                    ->visibility('public')
                    ->nullable(),
            ]);
    }
}