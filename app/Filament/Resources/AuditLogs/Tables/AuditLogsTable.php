<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class AuditLogsTable
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

                Filter::make('advanced')
                    ->label('Filtros avanzados')
                    ->schema([
                        DatePicker::make('from')
                            ->label('Desde'),
                        DatePicker::make('until')
                            ->label('Hasta'),
                        TextInput::make('actor_nick')
                            ->label('Usuario / actor')
                            ->placeholder('Nick exacto o parcial'),
                        TextInput::make('subject_table')
                            ->label('Tabla afectada')
                            ->placeholder('community_posts…'),
                        TextInput::make('subject_type')
                            ->label('Modelo afectado')
                            ->placeholder('CommunityPost…'),
                        TextInput::make('subject_id')
                            ->label('ID del objeto')
                            ->integer()
                            ->minValue(1),
                        Select::make('request_method')
                            ->label('Método HTTP')
                            ->options([
                                'GET' => 'GET',
                                'POST' => 'POST',
                                'PUT' => 'PUT',
                                'PATCH' => 'PATCH',
                                'DELETE' => 'DELETE',
                            ]),
                        TextInput::make('route_name')
                            ->label('Ruta Laravel')
                            ->placeholder('community.diary…'),
                        TextInput::make('ip_address')
                            ->label('Dirección IP'),
                        TextInput::make('correlation_id')
                            ->label('Correlation ID'),
                        TextInput::make('changed_field')
                            ->label('Campo modificado')
                            ->placeholder('comment, status_id…'),
                    ])
                    ->columns(3)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $query, $date): Builder => $query
                                    ->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, $date): Builder => $query
                                    ->whereDate('created_at', '<=', $date),
                            )
                            ->when(
                                $data['actor_nick'] ?? null,
                                fn (Builder $query, string $value): Builder => $query
                                    ->where('actor_nick', 'like', '%' . trim($value) . '%'),
                            )
                            ->when(
                                $data['subject_table'] ?? null,
                                fn (Builder $query, string $value): Builder => $query
                                    ->where('subject_table', 'like', '%' . trim($value) . '%'),
                            )
                            ->when(
                                $data['subject_type'] ?? null,
                                fn (Builder $query, string $value): Builder => $query
                                    ->where('subject_type', 'like', '%' . trim($value) . '%'),
                            )
                            ->when(
                                $data['subject_id'] ?? null,
                                fn (Builder $query, $value): Builder => $query
                                    ->where('subject_id', (int) $value),
                            )
                            ->when(
                                $data['request_method'] ?? null,
                                fn (Builder $query, string $value): Builder => $query
                                    ->where('request_method', $value),
                            )
                            ->when(
                                $data['route_name'] ?? null,
                                fn (Builder $query, string $value): Builder => $query
                                    ->where('route_name', 'like', '%' . trim($value) . '%'),
                            )
                            ->when(
                                $data['ip_address'] ?? null,
                                fn (Builder $query, string $value): Builder => $query
                                    ->where('ip_address', trim($value)),
                            )
                            ->when(
                                $data['correlation_id'] ?? null,
                                fn (Builder $query, string $value): Builder => $query
                                    ->where('correlation_id', trim($value)),
                            )
                            ->when(
                                $data['changed_field'] ?? null,
                                fn (Builder $query, string $value): Builder => $query
                                    ->where('attribute_changes', 'like', '%"' . trim($value) . '"%'),
                            );
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make()
                    ->label('Ver detalle'),
            ]);
    }
}
