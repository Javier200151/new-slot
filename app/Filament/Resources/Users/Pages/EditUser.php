<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\MemberProcedures\MemberProcedureResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\MemberProcedure;
use App\Models\Promo;
use App\Models\User;
use App\Services\MemberProcedures\MemberProcedureEngine;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Throwable;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('startRecruitmentProcedure')
                ->label('Ejecutar alta de recluta')
                ->icon('heroicon-o-academic-cap')
                ->color('info')
                ->visible(fn (): bool => $this->canStartProcedure(MemberProcedure::TYPE_RECRUITMENT_START))
                ->requiresConfirmation()
                ->modalDescription('Se ejecutarán los pasos automáticos y quedarán señalados los pasos manuales pendientes.')
                ->action(fn (MemberProcedureEngine $engine) => $this->startProcedure($engine, MemberProcedure::TYPE_RECRUITMENT_START)),

            Action::make('completeRecruitmentProcedure')
                ->label('Completar reclutamiento')
                ->icon('heroicon-o-user-plus')
                ->color('success')
                ->visible(fn (): bool => $this->canStartProcedure(MemberProcedure::TYPE_RECRUITMENT_COMPLETE) && $this->statusName() === 'RECLUTA')
                ->form([
                    Select::make('promo_id')
                        ->label('Promoción')
                        ->options(fn (): array => Promo::query()->orderBy('id')->pluck('id', 'id')->map(fn ($id): string => 'Promoción ' . $id)->all())
                        ->searchable()->required()
                        ->helperText('La promoción se asignará antes de pasar al usuario a ACTIVO.'),
                ])
                ->modalHeading('Completar reclutamiento')
                ->modalDescription('Los pasos automáticos se ejecutarán inmediatamente. Los externos que todavía requieran intervención quedarán como checklist.')
                ->action(fn (array $data, MemberProcedureEngine $engine) => $this->startProcedure($engine, MemberProcedure::TYPE_RECRUITMENT_COMPLETE, ['promo_id' => (int) $data['promo_id']])),

            Action::make('reserveProcedure')
                ->label('Ejecutar reserva')
                ->icon('heroicon-o-pause-circle')
                ->color('warning')
                ->visible(fn (): bool => $this->canStartProcedure(MemberProcedure::TYPE_RESERVE) && $this->statusName() === 'ACTIVO')
                ->requiresConfirmation()
                ->action(fn (MemberProcedureEngine $engine) => $this->startProcedure($engine, MemberProcedure::TYPE_RESERVE)),

            Action::make('reactivationProcedure')
                ->label('Ejecutar reactivación')
                ->icon('heroicon-o-play-circle')
                ->color('success')
                ->visible(fn (): bool => $this->canStartProcedure(MemberProcedure::TYPE_REACTIVATION) && $this->statusName() === 'RESERVA')
                ->requiresConfirmation()
                ->action(fn (MemberProcedureEngine $engine) => $this->startProcedure($engine, MemberProcedure::TYPE_REACTIVATION)),

            Action::make('departureProcedure')
                ->label('Tramitar baja')
                ->icon('heroicon-o-arrow-right-start-on-rectangle')
                ->color('gray')
                ->visible(fn (): bool => $this->canStartProcedure(MemberProcedure::TYPE_DEPARTURE) && ! in_array($this->statusName(), ['BAJA', 'CESADO'], true))
                ->form([
                    Textarea::make('reason')->label('Motivo / nota administrativa')->required()->maxLength(2000),
                ])
                ->requiresConfirmation()
                ->action(fn (array $data, MemberProcedureEngine $engine) => $this->startProcedure($engine, MemberProcedure::TYPE_DEPARTURE, ['reason' => trim((string) $data['reason'])])),

            Action::make('dismissalProcedure')
                ->label('Tramitar cese')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->visible(fn (): bool => $this->canStartProcedure(MemberProcedure::TYPE_DISMISSAL) && ! in_array($this->statusName(), ['BAJA', 'CESADO'], true))
                ->form([
                    Textarea::make('reason')->label('Motivo / nota administrativa')->required()->maxLength(2000),
                    Checkbox::make('ban_required')->label('Este cese requiere bloqueos/baneos en servicios externos'),
                ])
                ->requiresConfirmation()
                ->action(fn (array $data, MemberProcedureEngine $engine) => $this->startProcedure($engine, MemberProcedure::TYPE_DISMISSAL, [
                    'reason' => trim((string) $data['reason']),
                    'ban_required' => (bool) ($data['ban_required'] ?? false),
                ])),

            DeleteAction::make(),
        ];
    }

    private function canStartProcedure(string $type): bool
    {
        if (! auth()->user()?->can('member-procedures.create')) {
            return false;
        }

        return ! $this->record->memberProcedures()
            ->whereIn('status', [MemberProcedure::STATUS_IN_PROGRESS, MemberProcedure::STATUS_ERROR])
            ->exists();
    }

    private function statusName(): string
    {
        $this->record->loadMissing('status');

        return mb_strtoupper(trim((string) $this->record->status?->name));
    }

    private function startProcedure(MemberProcedureEngine $engine, string $type, array $input = []): void
    {
        try {
            /** @var User $user */
            $user = $this->record;
            $procedure = $engine->start($user, $type, $input, auth()->id());

            Notification::make()
                ->success()
                ->title('Procedimiento iniciado')
                ->body($procedure->typeLabel() . ' · ' . $procedure->statusLabel())
                ->send();

            $this->redirect(MemberProcedureResource::getUrl('edit', ['record' => $procedure]));
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()
                ->danger()
                ->title('No se pudo iniciar el procedimiento')
                ->body($exception->getMessage())
                ->send();
        }
    }
}
