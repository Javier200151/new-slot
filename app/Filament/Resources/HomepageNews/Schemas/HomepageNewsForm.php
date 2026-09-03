<?php

namespace App\Filament\Resources\HomepageNews\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class HomepageNewsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Contenido')->schema([
                TextInput::make('title')->label('Título')->required()->maxLength(180),
                Textarea::make('excerpt')->label('Entradilla')->rows(3)->maxLength(600)->columnSpanFull(),
                RichEditor::make('body')->label('Contenido')->columnSpanFull(),
                FileUpload::make('image')->label('Imagen')->image()->directory('homepage/news')->columnSpanFull(),
                TextInput::make('external_url')->label('Enlace externo / Instagram')->url()->maxLength(255),
            ])->columns(2),
            Section::make('Publicación')->schema([
                Toggle::make('is_published')->label('Publicada')->default(true),
                DateTimePicker::make('published_at')->label('Fecha de publicación')->seconds(false),
                TextInput::make('sort_order')->label('Orden')->numeric()->default(100)->minValue(0),
            ])->columns(3),
        ]);
    }
}
