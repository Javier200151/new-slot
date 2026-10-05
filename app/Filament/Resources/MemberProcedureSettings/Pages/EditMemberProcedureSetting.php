<?php

namespace App\Filament\Resources\MemberProcedureSettings\Pages;

use App\Filament\Resources\MemberProcedureSettings\MemberProcedureSettingResource;
use App\Models\MemberProcedureSetting;
use App\Services\MemberProcedures\GoogleSheetsService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Throwable;

class EditMemberProcedureSetting extends EditRecord
{
    protected static string $resource = MemberProcedureSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testGoogleSheets')
                ->label('Probar Google Sheets')
                ->icon('heroicon-o-cloud-arrow-up')
                ->color('info')
                ->action(function (GoogleSheetsService $googleSheets): void {
                    try {
                        /** @var MemberProcedureSetting $setting */
                        $setting = $this->record->fresh();
                        $result = $googleSheets->testConnection($setting);

                        Notification::make()
                            ->success()
                            ->title('Google Sheets conectado')
                            ->body('Lectura y escritura verificadas en la pestaña ' . ($result['sheet'] ?? 'General') . '.')
                            ->send();
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->danger()
                            ->title('Google Sheets no está listo')
                            ->body($exception->getMessage())
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }
}
