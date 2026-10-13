<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use App\Models\MemberProcedureSetting;
use App\Services\MemberProcedures\ArmaSquadsService;
use Throwable;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
