<?php

namespace App\Filament\Resources\MemberProcedures\RelationManagers;

use App\Models\MemberProcedureStep;
use App\Services\MemberProcedures\MemberProcedureEngine;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Throwable;

class StepsRelationManager extends RelationManager
{
    protected static string $relationship = 'steps';
    protected static ?string $title = 'Pasos del procedimiento';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('position')->label('#')->sortable(),
                TextColumn::make('label')->label('Paso')->wrap(),
                TextColumn::make('kind')
                    ->label('Tipo')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        MemberProcedureStep::KIND_AUTOMATIC => 'Automático',
                        MemberProcedureStep::KIND_MANUAL => 'Manual',
                        MemberProcedureStep::KIND_WAITING => 'Espera automática',
                        default => $state,
                    })->badge(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn (MemberProcedureStep $record): string => $record->statusLabel())
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        MemberProcedureStep::STATUS_COMPLETED => 'success',
                        MemberProcedureStep::STATUS_ERROR => 'danger',
                        MemberProcedureStep::STATUS_MANUAL, MemberProcedureStep::STATUS_WAITING => 'warning',
                        MemberProcedureStep::STATUS_SKIPPED => 'gray',
                        default => 'info',
                    }),
                TextColumn::make('attempts')->label('Intentos')->sortable(),
                TextColumn::make('instructions')
                    ->label('Instrucciones')
                    ->state(fn (MemberProcedureStep $record): ?string => ($record->meta ?? [])['instructions'] ?? null)
                    ->wrap()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('last_error')->label('Detalle / error')->wrap()->placeholder('—')->toggleable(),
                TextColumn::make('completedBy.nick')->label('Completado por')->placeholder('—')->toggleable(),
                TextColumn::make('completed_at')->label('Completado')->dateTime('d/m/Y H:i')->placeholder('—')->toggleable(),
            ])
            ->defaultSort('position')
            ->headerActions([])
            ->recordActions([
                Action::make('completeManual')
                    ->label('Marcar completado')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (MemberProcedureStep $record): bool => (bool) auth()->user()?->can('member-procedures.update')
                        && ! $record->isFinished()
                        && $record->status === MemberProcedureStep::STATUS_MANUAL)
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('note')
                            ->label('Nota opcional')
                            ->maxLength(1000)
                            ->helperText('Úsala para indicar qué se hizo manualmente. No introduzcas contraseñas, tokens ni datos personales sensibles.'),
                    ])
                    ->action(function (MemberProcedureStep $record, array $data, MemberProcedureEngine $engine): void {
                        try {
                            $engine->completeManualStep($record, note: $data['note'] ?? null);
                            Notification::make()->success()->title('Paso completado')->send();
                        } catch (Throwable $exception) {
                            report($exception);
                            Notification::make()->danger()->title('No se pudo completar el paso')->body($exception->getMessage())->send();
                        }
                    }),
                Action::make('retry')
                    ->label('Reintentar')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (MemberProcedureStep $record): bool => (bool) auth()->user()?->can('member-procedures.update')
                        && $record->kind !== MemberProcedureStep::KIND_MANUAL
                        && in_array($record->status, [MemberProcedureStep::STATUS_ERROR, MemberProcedureStep::STATUS_MANUAL, MemberProcedureStep::STATUS_WAITING], true))
                    ->action(function (MemberProcedureStep $record, MemberProcedureEngine $engine): void {
                        try {
                            $engine->retryStep($record);
                            Notification::make()->success()->title('Paso revisado')->send();
                        } catch (Throwable $exception) {
                            report($exception);
                            Notification::make()->danger()->title('No se pudo reintentar')->body($exception->getMessage())->send();
                        }
                    }),
            ]);
    }
}
