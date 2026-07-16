<?php

namespace App\Filament\Resources\Metopas\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Forms\Components\RichEditor;

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
                    TextInput::make('metopa_group')
                    ->label('Grupo')
                    ->maxLength(255),

                RichEditor::make('despag1')
                    ->label('Descripción página 1')
                    ->disableToolbarButtons([
                        'attachFiles',
                    ])
                    ->columnSpanFull(),

                RichEditor::make('despag2')
                    ->label('Descripción página 2')
                    ->disableToolbarButtons([
                        'attachFiles',
                    ])
                    ->columnSpanFull(),

                FileUpload::make('imgback')
                    ->label('Imagen de fondo')
                    ->image()
                    ->disk('public')
                    ->directory('metopas/backgrounds')
                    ->visibility('public')
                    ->imageEditor(),
            ]);
    }
}