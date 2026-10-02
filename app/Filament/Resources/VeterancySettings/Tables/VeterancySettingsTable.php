<?php

namespace App\Filament\Resources\VeterancySettings\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VeterancySettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('forumCategory.title')->label('Subcategoría')->default('Sin configurar'),
                TextColumn::make('bronzeMetopa.name')->label('Bronce')->default('Sin configurar'),
                TextColumn::make('silverMetopa.name')->label('Plata')->default('Sin configurar'),
                TextColumn::make('goldMetopa.name')->label('Oro')->default('Sin configurar'),
                TextColumn::make('updated_at')->label('Actualizado')->since(),
            ])
            ->paginated(false)
            ->recordActions([EditAction::make()]);
    }
}
