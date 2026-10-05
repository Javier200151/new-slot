<?php

namespace App\Filament\Resources\MemberProcedures\Pages;

use App\Filament\Resources\MemberProcedures\MemberProcedureResource;
use App\Models\MemberProcedure;
use App\Services\MemberProcedures\MemberProcedureEngine;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Throwable;

class EditMemberProcedure extends EditRecord
{
    protected static string $resource = MemberProcedureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshSteps')
                ->label('Revisar pasos automáticos')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (): bool => auth()->user()?->can('member-procedures.update')
                    && in_array($this->record->status, [MemberProcedure::STATUS_IN_PROGRESS, MemberProcedure::STATUS_ERROR], true))
                ->action(function (MemberProcedureEngine $engine): void {
                    $engine->runPending($this->record);
                    $this->record->refresh();
                    Notification::make()->success()->title('Procedimiento revisado')->send();
                    $this->redirect(MemberProcedureResource::getUrl('edit', ['record' => $this->record]));
                }),
            Action::make('cancelProcedure')
                ->label('Cancelar procedimiento')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->visible(fn (): bool => auth()->user()?->can('member-procedures.update')
                    && in_array($this->record->status, [MemberProcedure::STATUS_IN_PROGRESS, MemberProcedure::STATUS_ERROR], true))
                ->requiresConfirmation()
                ->form([
                    Textarea::make('reason')->label('Motivo')->required()->maxLength(1000),
                ])
                ->action(function (array $data, MemberProcedureEngine $engine): void {
                    $engine->cancel($this->record, (string) $data['reason']);
                    Notification::make()->success()->title('Procedimiento cancelado')->send();
                    $this->redirect(MemberProcedureResource::getUrl('edit', ['record' => $this->record]));
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return [];
    }

    protected function getFormActions(): array
    {
        return [];
    }
}
