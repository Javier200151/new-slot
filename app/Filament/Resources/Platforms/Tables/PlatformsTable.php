<?php

namespace App\Filament\Resources\Platforms\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlatformsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                ImageColumn::make('image')
                    ->label('Imagen')
                    ->state(fn ($record): ?string => $record->image
                        ? url('storage/' . $record->image)
                        : null)
                    ->imageWidth(50)  
                    ->imageHeight(50)
                    ->extraImgAttributes([
                        'style' => 'object-fit: contain;',
                    ]),
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
