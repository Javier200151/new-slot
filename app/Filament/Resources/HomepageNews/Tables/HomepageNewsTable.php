<?php

namespace App\Filament\Resources\HomepageNews\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HomepageNewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Título')->searchable()->wrap(),
                TextColumn::make('category')->label('Categoría')->formatStateUsing(fn ($state) => \App\Models\HomepageNews::CATEGORIES[$state] ?? 'Comunicado'),
                TextColumn::make('source_type')->label('Origen')->formatStateUsing(fn ($state) => $state === 'automatic' ? 'Automático' : 'Manual'),
                IconColumn::make('is_published')->label('Publicada')->boolean(),
                TextColumn::make('published_at')->label('Publicación')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('sort_order')->label('Orden')->sortable(),
            ])
            ->defaultSort('published_at', 'desc')
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
