<?php

namespace App\Filament\Resources\Factions\Tables;

use App\Models\Country;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),

                TextColumn::make('army.name')
                    ->label('Ejército')
                    ->searchable()
                    ->sortable(),

                ImageColumn::make('army.country.image')
                    ->label('País')
                    ->state(
                        fn ($record): ?string =>
                            $record->army?->country?->image
                                ? url(
                                    'storage/'
                                    . $record->army->country->image
                                )
                                : null
                    )
                    ->imageWidth(42)
                    ->imageHeight(28)
                    ->extraImgAttributes([
                        'style' => 'object-fit: contain;',
                    ]),

                TextColumn::make('side.name')
                    ->label('Bando')
                    ->searchable()
                    ->sortable(),

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
                SelectFilter::make('country_id')
                    ->label('País')
                    ->options(
                        fn (): array => Country::query()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all()
                    )
                    ->searchable()
                    ->preload()
                    ->query(
                        function (
                            Builder $query,
                            array $data,
                        ): Builder {
                            $countryId = $data['value'] ?? null;

                            if (blank($countryId)) {
                                return $query;
                            }

                            return $query->whereHas(
                                'army',
                                fn (Builder $armyQuery): Builder =>
                                    $armyQuery->where(
                                        'country_id',
                                        $countryId
                                    )
                            );
                        }
                    ),
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
