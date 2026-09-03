<?php

namespace App\Filament\Resources\HomepageSettings\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HomepageSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('recruitment_open')->label('Alistamiento')->boolean(),
                TextColumn::make('instagram_url')->label('Instagram')->limit(45),
                TextColumn::make('google_photos_url')->label('Google Fotos')->limit(45),
                TextColumn::make('updated_at')->label('Actualizado')->since(),
            ])
            ->recordActions([EditAction::make()]);
    }
}
