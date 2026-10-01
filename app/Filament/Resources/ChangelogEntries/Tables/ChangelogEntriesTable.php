<?php

namespace App\Filament\Resources\ChangelogEntries\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChangelogEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('release_date')->label('Fecha')->date('d/m/Y')->sortable(),
                TextColumn::make('version')->label('Versión')->placeholder('Sin versión')->searchable(),
                TextColumn::make('changes')->label('Cambios')->formatStateUsing(fn ($state): string => (string) count($state ?: [])),
                IconColumn::make('is_published')->label('Publicada')->boolean(),
                TextColumn::make('published_at')->label('Publicación')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('release_date', 'desc')
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
