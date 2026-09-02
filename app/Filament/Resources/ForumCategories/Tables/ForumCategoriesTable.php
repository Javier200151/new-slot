<?php

namespace App\Filament\Resources\ForumCategories\Tables;

use App\Models\ForumCategory;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ForumCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')
                    ->label('Orden')
                    ->sortable(),

                TextColumn::make('icon')
                    ->label('')
                    ->alignCenter(),

                TextColumn::make('title')
                    ->label('Categoría')
                    ->searchable()
                    ->sortable()
                    ->description(fn (ForumCategory $record): string => $record->slug),

                TextColumn::make('statuses.name')
                    ->label('Estados que la ven')
                    ->badge()
                    ->separator(', '),

                TextColumn::make('system_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        ForumCategory::TYPE_DIARY => 'Diarios',
                        ForumCategory::TYPE_CANTINA => 'Cantina',
                        ForumCategory::TYPE_DEBATE => 'Debates',
                        ForumCategory::TYPE_CALL => 'Convocatorias',
                        ForumCategory::TYPE_PROPOSAL => 'Propuestas',
                        ForumCategory::TYPE_CONSULTATION => 'Consultas',
                        default => 'Foro',
                    }),

                IconColumn::make('is_enabled')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(
                        fn (ForumCategory $record): bool =>
                            ! $record->is_system
                            && ! $record->posts()->exists()
                    ),
            ]);
    }
}
