<?php

namespace App\Filament\Resources\Activities\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Spatie\Activitylog\Models\Activity;

class ActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha y hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                TextColumn::make('log_name')
                    ->label('Tipo')
                    ->badge()
                    ->sortable(),

                TextColumn::make('actor_nick')
                    ->label('Quién')
                    ->default('Sistema')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('event')
                    ->label('Acción')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('subject_table')
                    ->label('Tabla')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('subject_label')
                    ->label('Elemento')
                    ->searchable(),

                TextColumn::make('subject_id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('ip_address')
                    ->label('IP')
                    ->searchable(),

                TextColumn::make('source')
                    ->label('Origen')
                    ->badge()
                    ->sortable(),

                TextColumn::make('route_name')
                    ->label('Ruta')
                    ->limit(35)
                    ->searchable()
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                TextColumn::make('correlation_id')
                    ->label('Correlation ID')
                    ->searchable()
                    ->copyable()
                    ->limit(14)
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),
            ])
            ->filters([
                SelectFilter::make('log_name')
                    ->label('Tipo de log')
                    ->options([
                        'audit' => 'Auditoría',
                        'security' => 'Seguridad',
                        'system' => 'Sistema',
                    ]),

                SelectFilter::make('event')
                    ->label('Acción')
                    ->options(
                        fn (): array =>
                            Activity::query()
                                ->whereNotNull('event')
                                ->distinct()
                                ->orderBy('event')
                                ->pluck(
                                    'event',
                                    'event'
                                )
                                ->all()
                    ),

                SelectFilter::make('source')
                    ->label('Origen')
                    ->options([
                        'web' => 'Web',
                        'api' => 'API',
                        'console' => 'Consola',
                        'system' => 'Sistema',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make()
                    ->label('Ver detalle'),
            ]);
    }
}
