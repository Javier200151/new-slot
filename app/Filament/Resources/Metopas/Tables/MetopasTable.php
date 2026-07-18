<?php

namespace App\Filament\Resources\Metopas\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class MetopasTable
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
                    ->imageWidth(86)  
                    ->imageHeight(25),  
                TextColumn::make('description')
                    ->searchable(),

                
                             
                // ImageColumn::make('image_large'),
                // TextColumn::make('despag1')
                //     ->searchable(),
                // TextColumn::make('despag2')
                //     ->searchable(),
                TextColumn::make('sqaGroup.name')
                    ->label('Grupo SQA')
                    ->searchable()
                    ->sortable(),
                // ImageColumn::make('imgback'),

                // TextColumn::make('created_at')
                //     ->dateTime()
                //     ->sortable()
                //     ->toggleable(isToggledHiddenByDefault: true),
                // TextColumn::make('updated_at')
                //     ->dateTime()
                //     ->sortable()
                //     ->toggleable(isToggledHiddenByDefault: true),
                // TextColumn::make('deleted_at')
                //     ->dateTime()
                //     ->sortable()
                //     ->toggleable(isToggledHiddenByDefault: true),
                // TextColumn::make('createdby.nick')
                //     ->label('Creado por')
                //     ->sortable()
                //     ->searchable(),
                // TextColumn::make('updatedBy.nick')
                //     ->label('Actualizado por')
                //     ->sortable()
                //     ->searchable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
