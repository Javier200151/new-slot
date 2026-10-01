<?php

namespace App\Filament\Resources\Metopas\Schemas;

use App\Filament\Forms\BbcodeTextarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
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
                    ->preserveFilenames()
                    ->required(),

                FileUpload::make('image_large')
                    ->label('Imagen grande')
                    ->image()
                    ->disk('public')
                    ->directory('metopas/large')
                    ->visibility('public')
                    ->preserveFilenames()
                    ->nullable(),

                Select::make('sqa_group_id')
                    ->label('Grupo SQA')
                    ->relationship('sqaGroup', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),

                BbcodeTextarea::make('despag1')
                    ->label('Descripción página 1')
                    ->columnSpanFull(),

                BbcodeTextarea::make('despag2')
                    ->label('Descripción página 2')
                    ->columnSpanFull(),

                FileUpload::make('imgback')
                    ->label('Imagen de fondo')
                    ->image()
                    ->disk('public')
                    ->directory('metopas/backgrounds')
                    ->visibility('public')
                    ->preserveFilenames()
                    ->imageEditor(),
            ]);
    }
}
