<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UserMetopasRelationManager extends RelationManager
{
    protected static string $relationship = 'userMetopas';

    protected static ?string $title = 'Metopas';

    public static function canViewForRecord(
        Model $ownerRecord,
        string $pageClass
    ): bool {
        return true;
    }
    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('metopa.name')
            ->columns([
                ImageColumn::make('metopa_image')
                    ->label('Imagen')
                    ->getStateUsing(
                        fn ($record): ?string =>
                            $record->metopa?->image
                                ? asset(
                                    'storage/'.$record->metopa->image
                                )
                                : null
                    )
                    ->size(40),

                TextColumn::make('metopa.name')
                    ->label('Metopa')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('assigned_at')
                    ->label('Fecha de asignación')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('assigned_at', 'asc')
            ->headerActions([])
            ->recordActions([]);
    }
}