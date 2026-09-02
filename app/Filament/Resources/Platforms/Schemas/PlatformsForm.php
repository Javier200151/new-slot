<?php

namespace App\Filament\Resources\Platforms\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PlatformsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                FileUpload::make('image')
                    ->image()
                    ->disk('public')
                    ->directory('platforms')
                    ->visibility('public')
                    ->imageResizeMode('contain')
                    ->imageResizeTargetWidth('256')
                    ->imageResizeTargetHeight('256')
                    ->preserveFilenames(),
            ]);
    }
}
