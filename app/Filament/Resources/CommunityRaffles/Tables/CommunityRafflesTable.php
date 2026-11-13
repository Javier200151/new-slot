<?php

namespace App\Filament\Resources\CommunityRaffles\Tables;

use App\Models\CommunityRaffle;
use App\Services\CommunityRaffleService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CommunityRafflesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Sorteo')->searchable()->wrap(),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'active' => 'Abierto', 'closed' => 'Finalizado', default => 'En preparación',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success', 'closed' => 'gray', default => 'warning',
                    }),
                TextColumn::make('prizes_count')->counts('prizes')->label('Premios'),
                TextColumn::make('created_at')->label('Creado')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                EditAction::make()->label('Editar'),
                Action::make('open_public')->label('Ver sorteo')->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (CommunityRaffle $record): string => route('raffles.show', $record))
                    ->openUrlInNewTab()->visible(fn (CommunityRaffle $record) => $record->status !== 'draft'),
                Action::make('activate')->label('Activar')->icon('heroicon-o-play')
                    ->color('success')->requiresConfirmation()
                    ->visible(fn (CommunityRaffle $record) => $record->status === 'draft')
                    ->action(fn (CommunityRaffle $record) => app(CommunityRaffleService::class)->activate($record)),
                Action::make('close')->label('Cerrar sorteo')->icon('heroicon-o-lock-closed')
                    ->color('warning')->requiresConfirmation()
                    ->visible(fn (CommunityRaffle $record) => $record->status === 'active')
                    ->action(fn (CommunityRaffle $record) => app(CommunityRaffleService::class)->close($record)),
                DeleteAction::make()->visible(fn (CommunityRaffle $record) => $record->status === 'draft' && ! $record->draws()->exists()),
            ]);
    }
}
