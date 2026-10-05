<?php

namespace App\Filament\Resources\MemberProcedures\Tables;

use App\Models\MemberProcedure;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MemberProceduresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.nick')->label('Miembro')->searchable()->sortable(),
                TextColumn::make('type')
                    ->label('Procedimiento')
                    ->formatStateUsing(fn (MemberProcedure $record): string => $record->typeLabel())
                    ->badge(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn (MemberProcedure $record): string => $record->statusLabel())
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        MemberProcedure::STATUS_COMPLETED => 'success',
                        MemberProcedure::STATUS_ERROR => 'danger',
                        MemberProcedure::STATUS_CANCELLED => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('progress')
                    ->label('Progreso')
                    ->state(function (MemberProcedure $record): string {
                        $total = $record->steps()->where('required', true)->count();
                        $done = $record->steps()->where('required', true)->whereIn('status', ['completed', 'skipped'])->count();

                        return $done . ' / ' . $total;
                    }),
                TextColumn::make('started_at')->label('Inicio')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('startedBy.nick')->label('Iniciado por')->default('Sistema'),
            ])
            ->defaultSort('started_at', 'desc')
            ->recordActions([EditAction::make()]);
    }
}
