<?php

namespace App\Filament\Resources\CommunityPolls\Tables;

use App\Models\CommunityPoll;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CommunityPollsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_published')
                    ->label('Publicada')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('process.title')
                    ->label('Proceso origen')
                    ->placeholder('Votación directa')
                    ->limit(35),

                TextColumn::make('selection_mode')
                    ->label('Selección')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === CommunityPoll::MODE_MULTIPLE
                        ? 'Múltiple'
                        : 'Única'),

                IconColumn::make('is_anonymous')
                    ->label('Anónima')
                    ->boolean(),

                IconColumn::make('allow_vote_change')
                    ->label('Editable')
                    ->boolean(),

                TextColumn::make('participants')
                    ->label('Participantes')
                    ->getStateUsing(fn (CommunityPoll $record): int => $record->votes()
                        ->distinct('user_id')
                        ->count('user_id')),

                TextColumn::make('starts_at')
                    ->label('Inicio')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Inmediato')
                    ->sortable(),

                TextColumn::make('ends_at')
                    ->label('Cierre')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Sin cierre')
                    ->sortable(),

                TextColumn::make('createdBy.nick')
                    ->label('Creada por')
                    ->placeholder('Sistema'),
            ])
            ->filters([
                TernaryFilter::make('is_published')
                    ->label('Publicación')
                    ->trueLabel('Publicadas')
                    ->falseLabel('Borradores'),

                TernaryFilter::make('is_anonymous')
                    ->label('Anonimato')
                    ->trueLabel('Anónimas')
                    ->falseLabel('Nominales'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
