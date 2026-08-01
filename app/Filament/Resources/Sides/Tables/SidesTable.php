<?php

namespace App\Filament\Resources\Sides\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SidesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                ImageColumn::make('image')
                    ->state(fn ($record): ?string => $record->image
                        ? url('storage/' . $record->image)
                        : null)
                    ->imageWidth(50)  
                    ->imageHeight(50)
                    ->extraImgAttributes([
                        'style' => 'object-fit: contain;',
                    ]),
                TextColumn::make('description')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
