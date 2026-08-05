<?php

namespace App\Filament\Resources\Pages\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Título')
                    ->required()
                    ->maxLength(255),

                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->helperText('Identificador único que se utilizará en la URL.'),

                Toggle::make('is_published')
                    ->label('Publicada')
                    ->default(false),

                RichEditor::make('content')
                    ->label('Contenido')
                    ->required()
                    ->disableToolbarButtons([
                        'attachFiles',
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
