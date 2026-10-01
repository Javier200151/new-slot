<?php

namespace App\Filament\Resources\Addons\Tables;

use App\Support\BbcodeMarkup;
use Illuminate\Support\Str;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AddonsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                

                IconColumn::make('mandatory')
                    ->label('Obligatorio')
                    ->boolean()
                    ->sortable(),

                IconColumn::make('active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Descripción')
                    ->formatStateUsing(fn ($state): string => Str::limit(
                        trim(strip_tags(BbcodeMarkup::render((string) $state)->toHtml())),
                        80
                    ))
                    ->searchable(),    

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
