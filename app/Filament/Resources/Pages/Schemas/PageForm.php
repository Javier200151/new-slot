<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Filament\Forms\BbcodeTextarea;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
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

                Select::make('template')
                    ->label('Tipo de página')
                    ->options([
                        'content' => 'Contenido normal',
                        'treasury' => 'Tesorería',
                    ])
                    ->default('content')
                    ->required()
                    ->helperText('Tesorería mantiene el texto introductorio editable y añade automáticamente el resumen económico y los gastos públicos.'),

                Toggle::make('is_published')
                    ->label('Publicada')
                    ->default(false),

                BbcodeTextarea::make('content')
                    ->label('Contenido')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
