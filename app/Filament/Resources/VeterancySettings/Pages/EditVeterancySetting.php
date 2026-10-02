<?php

namespace App\Filament\Resources\VeterancySettings\Pages;

use App\Filament\Resources\VeterancySettings\VeterancySettingResource;
use App\Services\VeterancyHistoryImportService;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;
use Throwable;

class EditVeterancySetting extends EditRecord
{
    protected static string $resource = VeterancySettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importHistory')
                ->label('Importar historial')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->form([
                    FileUpload::make('history_file')
                        ->label('Archivo histórico')
                        ->disk('local')
                        ->directory('veterancy-imports')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'text/csv',
                            'application/csv',
                            'text/plain',
                            'application/vnd.oasis.opendocument.spreadsheet',
                        ])
                        ->helperText('Columnas esperadas: NOMBRE, FECHA, ESTADO y opcionalmente RE-TUTO POR. Formatos admitidos: XLSX, CSV y ODS.')
                        ->required(),
                    Checkbox::make('dry_run')
                        ->label('Solo comprobar (dry-run)')
                        ->helperText('Analiza el archivo y muestra el resultado sin guardar cambios.'),
                ])
                ->modalHeading('Importar historial ACTIVO / RESERVA')
                ->modalSubmitActionLabel('Procesar archivo')
                ->action(function (array $data, VeterancyHistoryImportService $service): void {
                    $storedPath = (string) $data['history_file'];
                    $absolutePath = Storage::disk('local')->path($storedPath);

                    try {
                        $result = $service->import($absolutePath, (bool) ($data['dry_run'] ?? false));
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->danger()
                            ->title('No se pudo importar el historial')
                            ->body($exception->getMessage())
                            ->send();

                        return;
                    } finally {
                        Storage::disk('local')->delete($storedPath);
                    }

                    $details = [
                        ($result['dry_run'] ? 'Dry-run' : 'Importación') . ': ' . $result['imported'] . ' filas válidas',
                        $result['duplicates'] . ' duplicadas/sin cambio',
                        $result['before_member_at'] . ' anteriores a Miembro desde',
                    ];

                    if ($result['not_found'] !== []) {
                        $details[] = 'Nicks no encontrados: ' . implode(', ', array_slice($result['not_found'], 0, 20));
                    }
                    if ($result['without_member_at'] !== []) {
                        $details[] = 'Sin Miembro desde: ' . implode(', ', array_slice($result['without_member_at'], 0, 20));
                    }
                    if ($result['invalid'] !== []) {
                        $details[] = 'Filas inválidas: ' . implode(' | ', array_slice($result['invalid'], 0, 10));
                    }

                    Notification::make()
                        ->success()
                        ->title($result['dry_run'] ? 'Comprobación terminada' : 'Historial importado')
                        ->body(implode("\n", $details))
                        ->persistent()
                        ->send();
                }),
        ];
    }
}
