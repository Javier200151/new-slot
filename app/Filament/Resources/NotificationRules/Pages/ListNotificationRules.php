<?php

namespace App\Filament\Resources\NotificationRules\Pages;

use App\Filament\Resources\NotificationRules\NotificationRuleResource;
use App\Models\NotificationRule;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Validation\ValidationException;

class ListNotificationRules extends ListRecords
{
    protected static string $resource = NotificationRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('allRules')
                ->label('Aplicar a todas')
                ->icon('heroicon-o-adjustments-horizontal')
                ->visible(fn (): bool => NotificationRuleResource::canManage())
                ->requiresConfirmation()
                ->modalHeading('Cambiar todas las notificaciones')
                ->modalDescription('Elige qué ajuste aplicar. Los límites temporales solo afectan a avisos con fecha de referencia.')
                ->form([
                    Select::make('operation')->label('Cambio')->required()
                        ->options([
                            'enable' => 'Activar todas',
                            'disable' => 'Desactivar todas',
                            'age' => 'Cambiar antigüedad',
                        ])->live(),
                    TextInput::make('max_age_value')->label('Antigüedad máxima')->numeric()->integer()->minValue(1)->maxValue(3650)
                        ->placeholder('Vacío: sin límite')
                        ->visible(fn ($get): bool => $get('operation') === 'age'),
                    Select::make('max_age_unit')->label('Unidad')
                        ->options(['days'=>'Días','weeks'=>'Semanas','months'=>'Meses','years'=>'Años'])
                        ->default('months')->required()
                        ->visible(fn ($get): bool => $get('operation') === 'age'),
                ])
                ->action(function (array $data): void {
                    abort_unless(NotificationRuleResource::canManage(), 403);
                    $operation = $data['operation'];
                    $values = match ($operation) {
                        'enable' => ['enabled' => true],
                        'disable' => ['enabled' => false],
                        'age' => [
                            'max_age_value' => filled($data['max_age_value'] ?? null) ? (int) $data['max_age_value'] : null,
                            'max_age_unit' => $data['max_age_unit'] ?? 'months',
                        ],
                        default => throw ValidationException::withMessages(['operation' => 'Selecciona una opción válida.']),
                    };
                    $count = 0;
                    NotificationRule::query()->chunkById(100, function ($records) use ($values, $operation, &$count): void {
                        foreach ($records as $record) {
                            if ($operation === 'age' && ! $record->allows_age) continue;
                            $record->update($values);
                            $count++;
                        }
                    }, column: 'key');
                    Notification::make()->success()->title('Cambios aplicados')
                        ->body($count . ' tipos de notificación actualizados.')->send();
                }),
        ];
    }
}
