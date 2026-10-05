<?php

namespace App\Filament\Resources\MemberProcedureSettings\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MemberProcedureSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('alphaMetopa.name')->label('Metopa ALPHA')->default('Sin configurar'),
                TextColumn::make('treasuryGroup.name')->label('Tesorería')->default('Sin configurar'),
                TextColumn::make('tutorsGroup.name')->label('Tutores')->default('Sin configurar'),
                TextColumn::make('armasquads_squad_id')->label('ArmaSquads')->default('Sin configurar'),
                TextColumn::make('updated_at')->label('Actualizado')->since(),
            ])
            ->paginated(false)
            ->recordActions([EditAction::make()]);
    }
}
