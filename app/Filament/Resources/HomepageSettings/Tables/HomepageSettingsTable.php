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
                TextColumn::make('instagram_url')->label('Instagram')->limit(32),
                TextColumn::make('discord_invite_url')->label('Discord')->limit(38),
                IconColumn::make('discord_invite_auto_refresh')->label('Discord auto')->boolean(),
                TextColumn::make('updated_at')->label('Actualizado')->since(),
            ])
            ->recordActions([EditAction::make()]);
    }
}
