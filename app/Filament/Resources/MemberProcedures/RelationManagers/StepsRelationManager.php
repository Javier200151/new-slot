<?php

namespace App\Filament\Resources\MemberProcedures\RelationManagers;

use App\Models\MemberProcedureStep;
use App\Services\MemberProcedures\GoogleSheetsService;
use App\Services\MemberProcedures\MemberProcedureEngine;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Throwable;

class StepsRelationManager extends RelationManager
{
    protected static string $relationship = 'steps';
    protected static ?string $title = 'Pasos del procedimiento';

    public function completeManualFromCard(int $stepId): void
    {
        abort_unless((bool) auth()->user()?->can('member-procedures.update'), 403);

        /** @var MemberProcedureStep $step */
        $step = $this->getOwnerRecord()->steps()->whereKey($stepId)->firstOrFail();

        try {
            app(MemberProcedureEngine::class)->completeManualStep($step);
            Notification::make()->success()->title('Paso completado')->send();
            $this->resetTable();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->danger()->title('No se pudo completar el paso')->body($exception->getMessage())->send();
        }
    }

    public function retryFromCard(int $stepId): void
    {
        abort_unless((bool) auth()->user()?->can('member-procedures.update'), 403);

        /** @var MemberProcedureStep $step */
        $step = $this->getOwnerRecord()->steps()->whereKey($stepId)->firstOrFail();

        try {
            app(MemberProcedureEngine::class)->retryStep($step);
            Notification::make()->success()->title('Paso revisado')->send();
            $this->resetTable();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->danger()->title('No se pudo reintentar')->body($exception->getMessage())->send();
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                ViewColumn::make('step_card')
                    ->label('Paso')
                    ->view('filament.member-procedures.step-card'),
            ])
            ->contentGrid([
                'default' => 1,
            ])
            ->defaultSort('position')
            ->headerActions([])
            ->recordActions([
                Action::make('googleManualRow')
                    ->label('Fila manual')
                    ->icon('heroicon-o-clipboard-document')
                    ->color('gray')
                    ->visible(fn (MemberProcedureStep $record): bool => (bool) auth()->user()?->can('member-procedures.update')
                        && $record->step_key === 'google_sheets_transfer'
                        && ! $record->isFinished())
                    ->modalHeading('Fila de contingencia para Google Sheets')
                    ->modalDescription('Selecciona todo el contenido del campo y pégalo empezando en la columna A de una fila vacía de la pestaña General. Los valores están separados por tabuladores.')
                    ->form(function (MemberProcedureStep $record): array {
                        $procedure = $record->procedure()->with('user')->firstOrFail();
                        $row = app(GoogleSheetsService::class)->manualTsvForUser($procedure->user);

                        return [
                            Textarea::make('row')
                                ->label('Fila A:Q')
                                ->default($row)
                                ->rows(5)
                                ->dehydrated(false)
                                ->extraInputAttributes([
                                    'readonly' => true,
                                    'onclick' => 'this.select()',
                                ]),
                        ];
                    })
                    ->modalSubmitActionLabel('Cerrar')
                    ->action(fn (): null => null),
                Action::make('completeManual')
                    ->label('Marcar completado')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (MemberProcedureStep $record): bool => (bool) auth()->user()?->can('member-procedures.update')
                        && ! $record->isFinished()
                        && (
                            $record->status === MemberProcedureStep::STATUS_MANUAL
                            || (str_starts_with((string) $record->step_key, 'google_sheets_') && $record->status === MemberProcedureStep::STATUS_ERROR)
                        ))
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
