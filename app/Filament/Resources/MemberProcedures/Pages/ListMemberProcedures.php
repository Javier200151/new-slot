<?php

namespace App\Filament\Resources\MemberProcedures\Pages;

use App\Filament\Resources\MemberProcedures\MemberProcedureResource;
use App\Models\MemberProcedure;
use App\Models\Promo;
use App\Models\User;
use App\Services\MemberProcedures\MemberProcedureEligibility;
use App\Services\MemberProcedures\MemberProcedureEngine;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Throwable;

class ListMemberProcedures extends ListRecords
{
    protected static string $resource = MemberProcedureResource::class;

    protected function getHeaderActions(): array
    {
        if (! auth()->user()?->can('member-procedures.create')) {
            return [];
        }

        return [
            Action::make('startRecruitment')
                ->label('Alta de recluta')
                ->icon('heroicon-o-academic-cap')
                ->color('info')
                ->modalHeading('Alta de recluta')
                ->modalDescription('Solo aparecen usuarios en estado USUARIO con una solicitud de alistamiento aprobada y vinculada, y sin otro procedimiento abierto.')
                ->form([
                    $this->userSelect(MemberProcedure::TYPE_RECRUITMENT_START, 'Usuario que inicia el reclutamiento'),
                ])
                ->action(fn (array $data, MemberProcedureEngine $engine) => $this->startProcedure($engine, MemberProcedure::TYPE_RECRUITMENT_START, $data)),

            Action::make('completeRecruitment')
                ->label('Alta de calavera')
                ->icon('heroicon-o-user-plus')
                ->color('success')
                ->modalHeading('Alta de calavera · pasar a ACTIVO')
                ->modalDescription('Solo aparecen usuarios que actualmente están en RECLUTA y no tienen otro procedimiento abierto.')
                ->form([
                    $this->userSelect(MemberProcedure::TYPE_RECRUITMENT_COMPLETE, 'Recluta que pasa a ACTIVO'),
                    Select::make('promo_id')
                        ->label('Promoción')
                        ->options(fn (): array => Promo::query()->orderBy('id')->pluck('id', 'id')->map(fn ($id): string => 'Promoción ' . $id)->all())
                        ->searchable()
                        ->required()
                        ->createOptionModalHeading('Crear nueva promoción')
                        ->createOptionForm([
                            TextInput::make('id')
                                ->label('Número de promoción')
                                ->numeric()
                                ->default(fn (): int => ((int) Promo::query()->max('id')) + 1)
                                ->rules(['required', 'integer', 'min:1', 'max:65535', 'unique:promo,id'])
                                ->required(),
                            FileUpload::make('image')
                                ->label('Imagen')
                                ->image()
                                ->disk('public')
                                ->directory('promos')
                                ->visibility('public')
                                ->preserveFilenames()
                                ->required(),
                        ])
                        ->createOptionUsing(function (array $data): int {
                            $promo = Promo::query()->create([
                                'id' => (int) $data['id'],
                                'image' => (string) $data['image'],
                            ]);

                            return (int) $promo->getKey();
                        }),
                ])
                ->action(fn (array $data, MemberProcedureEngine $engine) => $this->startProcedure($engine, MemberProcedure::TYPE_RECRUITMENT_COMPLETE, $data, [
                    'promo_id' => (int) $data['promo_id'],
                ])),

            Action::make('reserve')
                ->label('Reserva')
                ->icon('heroicon-o-pause-circle')
                ->color('warning')
                ->modalHeading('Pasar miembro a reserva')
                ->modalDescription('Solo aparecen miembros en estado ACTIVO y sin otro procedimiento abierto.')
                ->form([
                    $this->userSelect(MemberProcedure::TYPE_RESERVE, 'Miembro ACTIVO'),
                ])
                ->action(fn (array $data, MemberProcedureEngine $engine) => $this->startProcedure($engine, MemberProcedure::TYPE_RESERVE, $data)),

            Action::make('reactivation')
                ->label('Reactivación')
                ->icon('heroicon-o-play-circle')
                ->color('success')
                ->modalHeading('Reactivar miembro')
                ->modalDescription('Solo aparecen miembros en estado RESERVA y sin otro procedimiento abierto.')
                ->form([
                    $this->userSelect(MemberProcedure::TYPE_REACTIVATION, 'Miembro en RESERVA'),
                ])
                ->action(fn (array $data, MemberProcedureEngine $engine) => $this->startProcedure($engine, MemberProcedure::TYPE_REACTIVATION, $data)),

            Action::make('departure')
                ->label('Baja')
                ->icon('heroicon-o-arrow-right-start-on-rectangle')
                ->color('gray')
                ->modalHeading('Tramitar baja')
                ->modalDescription('Solo aparecen RECLUTAS, ACTIVOS o RESERVAS sin otro procedimiento abierto.')
                ->form([
                    $this->userSelect(MemberProcedure::TYPE_DEPARTURE, 'Usuario'),
                    Textarea::make('reason')
                        ->label('Motivo / nota administrativa')
                        ->required()
                        ->maxLength(2000),
                ])
                ->action(fn (array $data, MemberProcedureEngine $engine) => $this->startProcedure($engine, MemberProcedure::TYPE_DEPARTURE, $data, [
                    'reason' => trim((string) $data['reason']),
                ])),

            Action::make('dismissal')
                ->label('Cese')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->modalHeading('Tramitar cese')
                ->modalDescription('Solo aparecen RECLUTAS, ACTIVOS o RESERVAS sin otro procedimiento abierto.')
                ->form([
                    $this->userSelect(MemberProcedure::TYPE_DISMISSAL, 'Usuario'),
                    Textarea::make('reason')
                        ->label('Motivo / nota administrativa')
                        ->required()
                        ->maxLength(2000),
                    Checkbox::make('ban_required')
                        ->label('Este cese requiere bloqueos/baneos en servicios externos'),
                ])
                ->action(fn (array $data, MemberProcedureEngine $engine) => $this->startProcedure($engine, MemberProcedure::TYPE_DISMISSAL, $data, [
                    'reason' => trim((string) $data['reason']),
                    'ban_required' => (bool) ($data['ban_required'] ?? false),
                ])),
        ];
    }

    private function userSelect(string $type, string $label): Select
    {
        return Select::make('user_id')
            ->label($label)
            ->options(fn (): array => app(MemberProcedureEligibility::class)->options($type))
            ->searchable()
            ->preload()
            ->required()
            ->helperText('La lista se filtra automáticamente según el estado y los requisitos del procedimiento.');
    }

    private function startProcedure(
        MemberProcedureEngine $engine,
        string $type,
        array $data,
        array $input = [],
    ): void {
        try {
            $user = User::query()->findOrFail((int) $data['user_id']);
            $procedure = $engine->start($user, $type, $input, auth()->id());

            Notification::make()
                ->success()
                ->title('Procedimiento iniciado')
                ->body($user->nick . ' · ' . $procedure->typeLabel() . ' · ' . $procedure->statusLabel())
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
