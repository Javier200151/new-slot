<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Models\UserStatusHistory;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StatusHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'statusHistory';

    protected static ?string $title = 'Historial de estados';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('changed_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('fromStatus.name')
                    ->label('Estado anterior')
                    ->default('—')
                    ->badge(),
                TextColumn::make('toStatus.name')
                    ->label('Estado nuevo')
                    ->badge(),
                TextColumn::make('changedBy.nick')
                    ->label('Realizado por')
                    ->default('Sistema'),
                TextColumn::make('source')
                    ->label('Origen')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'automatic' => 'Cambio de estado',
                        'historical_import' => 'Importación histórica',
                        'baseline' => 'Estado inicial del sistema',
                        default => $state,
                    })
                    ->badge(),
                TextColumn::make('retutored_by')
                    ->label('RE-TUTO POR')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('changed_at', 'desc')
            ->headerActions([])
            ->recordActions([]);
    }
}
