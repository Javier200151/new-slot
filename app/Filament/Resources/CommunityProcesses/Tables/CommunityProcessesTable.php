<?php

namespace App\Filament\Resources\CommunityProcesses\Tables;

use App\Models\CommunityProcess;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CommunityProcessesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Título')->searchable()->sortable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => CommunityProcess::typeOptions()[$state] ?? $state),
                TextColumn::make('public_status')
                    ->label('Estado')
                    ->badge()
                    ->state(fn (CommunityProcess $record): string => CommunityProcess::statusOptions()[$record->effectiveStatus()] ?? $record->effectiveStatus()),
                TextColumn::make('active_applications_count')
                    ->label('Postulaciones')
                    ->state(fn (CommunityProcess $record): int => $record->activeApplications()->count()),
                IconColumn::make('poll')->label('Votación')->boolean()->state(fn (CommunityProcess $record): bool => (bool) $record->poll),
                TextColumn::make('applications_end_at')->label('Cierre')->dateTime('d/m/Y H:i')->placeholder('Sin cierre'),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
