<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use App\Models\MemberProcedureSetting;
use App\Services\MemberProcedures\ArmaSquadsService;
use App\Services\SignatureBannerGenerator;
use App\Services\RecruitmentParticipationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Throwable;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('memberPlayedEvents')
                ->label('Eventos jugados')
                ->icon('heroicon-o-calendar-days')
                ->color('gray')
                ->modalHeading(fn (): string => 'Eventos jugados · ' . $this->record->nick)
                ->modalWidth('4xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->modalContent(function () {
                    $user = $this->record->fresh();
                    Gate::authorize('update', $user);

                    return view('filament.recruitment.member-events-list', [
                        'events' => app(RecruitmentParticipationService::class)->playedEventDetails(
                            (int) $user->getKey(),
                            Carbon::create(1900, 1, 1, 0, 0, 0),
                        ),
                    ]);
                }),
            Action::make('regenerateSignature')
                ->label('Regenerar firma')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn (): bool => ! in_array(
                    strtoupper(trim((string) $this->record->status?->name)),
                    ['USUARIO', 'RECLUTA', 'ALIADO'],
                    true,
                ))
                ->requiresConfirmation()
                ->modalHeading('Regenerar firma')
                ->modalDescription('Se volverá a crear la imagen de la firma de este miembro.')
                ->action(function (SignatureBannerGenerator $generator): void {
                    $user = $this->record->fresh(['status']);
                    Gate::authorize('update', $user);

                    if (in_array(strtoupper(trim((string) $user->status?->name)), ['USUARIO', 'RECLUTA', 'ALIADO'], true)) {
                        Notification::make()->title('Este usuario no tiene una firma individual')->warning()->send();
                        return;
                    }

                    try {
                        $generator->generate($user);
                        if ($generator->needsRepair($user)) {
                            throw new \RuntimeException('La firma generada no es una imagen válida.');
                        }
                        Notification::make()->title('Firma regenerada correctamente')->success()->send();
                    } catch (Throwable $exception) {
                        report($exception);
                        Notification::make()
                            ->title('No se pudo regenerar la firma')
                            ->body('Comprueba los registros de la web e inténtalo de nuevo.')
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
            Action::make('verifyArmaSquads')
                ->label('Verificar / sincronizar ArmaSquads')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->visible(fn (): bool => strtoupper(trim((string) $this->record->status?->name)) === 'ACTIVO')
                ->requiresConfirmation()
                ->modalHeading('Verificar miembro en ArmaSquads')
                ->modalDescription('Comprueba el SteamID64 del usuario: si no existe, lo registra; si existe con otro nick, lo actualiza. No genera duplicados.')
                ->action(function (ArmaSquadsService $service): void {
                    $user = $this->record->fresh(['status']);
                    if (strtoupper(trim((string) $user?->status?->name)) !== 'ACTIVO') {
                        Notification::make()->title('Solo disponible para usuarios ACTIVO')->danger()->send();
                        return;
                    }
                    try {
                        $result = $service->upsert($user, MemberProcedureSetting::current());
                        $label = match ($result['action'] ?? null) {
                            'created' => 'Miembro añadido a ArmaSquads',
                            'updated' => 'Miembro actualizado en ArmaSquads',
                            'already_synced' => 'Miembro verificado: ya estaba sincronizado',
                            default => 'Verificación completada',
                        };
                        Notification::make()->title($label)->success()->send();
                    } catch (Throwable $exception) {
                        report($exception);
                        Notification::make()->title('No se pudo sincronizar ArmaSquads')
                            ->body($exception->getMessage())->danger()->persistent()->send();
                    }
                }),
            DeleteAction::make(),
        ];
    }
}
