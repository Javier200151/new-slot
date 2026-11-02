<?php

namespace App\Filament\Resources\MemberProcedures\Pages;

use App\Filament\Resources\MemberProcedures\MemberProcedureResource;
use App\Filament\Resources\MemberProcedures\Support\ProcedureForumThreadFields;
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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
                    $this->userSelect(MemberProcedure::TYPE_RECRUITMENT_START, 'Usuario que inicia el reclutamiento')
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set): void {
                            $set('recruit_nickname', User::query()->whereKey((int) $state)->value('nick') ?? '');
                        }),
                    TextInput::make('recruit_nickname')
                        ->label('Nickname que tendrá el recluta')
                        ->required()
                        ->minLength(3)
                        ->maxLength(30)
                        ->rules(fn (Get $get): array => [
                            'regex:/^[A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)*$/',
                            Rule::unique('users', 'nick')->ignore((int) $get('user_id')),
                        ])
                        ->validationMessages([
                            'required' => 'Indica el nickname definitivo del recluta.',
                            'min' => 'El nickname debe tener al menos 3 caracteres.',
                            'max' => 'El nickname no puede tener más de 30 caracteres.',
                            'regex' => 'Utiliza letras sin tildes, números, guiones, guiones bajos o puntos.',
                            'unique' => 'Este nickname ya lo utiliza otra cuenta.',
                        ])
                        ->helperText('Se rellena con el nick actual del usuario. Puedes cambiarlo antes del alta; será el nombre que se use en Squad ALPHA, Tesorería y las sincronizaciones.'),
                    ...ProcedureForumThreadFields::fields(),
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
                    ...ProcedureForumThreadFields::fields(),
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

            Action::make('notPromoted')
                ->label('No promocionado')
                ->icon('heroicon-o-user-minus')
                ->color('danger')
                ->modalHeading('Cerrar reclutamiento · NO PROMOCIONADO')
                ->modalDescription('Solo aparecen usuarios que actualmente están en RECLUTA y no tienen otro procedimiento abierto. El usuario pasará al estado NO PROMOCIONADO y el periodo de reclutamiento quedará cerrado con resultado NO PROMOCIONADO.')
                ->form([
                    $this->userSelect(MemberProcedure::TYPE_NOT_PROMOTED, 'Recluta que no promociona'),
                    Textarea::make('reason')
                        ->label('Motivo / nota administrativa')
                        ->required()
                        ->maxLength(2000),
                ])
                ->action(fn (array $data, MemberProcedureEngine $engine) => $this->startProcedure($engine, MemberProcedure::TYPE_NOT_PROMOTED, $data, [
                    'reason' => trim((string) $data['reason']),
                ])),

            Action::make('reserve')
                ->label('Reserva')
                ->icon('heroicon-o-pause-circle')
                ->color('warning')
                ->modalHeading('Pasar miembro a reserva')
                ->modalDescription('Solo aparecen miembros en estado ACTIVO y sin otro procedimiento abierto.')
                ->form([
                    $this->userSelect(MemberProcedure::TYPE_RESERVE, 'Miembro ACTIVO'),
                    ...ProcedureForumThreadFields::fields(),
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
                    ...ProcedureForumThreadFields::fields(),
                ])
                ->action(fn (array $data, MemberProcedureEngine $engine) => $this->startProcedure($engine, MemberProcedure::TYPE_REACTIVATION, $data)),

            Action::make('departure')
                ->label('Baja')
                ->icon('heroicon-o-arrow-right-start-on-rectangle')
                ->color('gray')
                ->modalHeading('Tramitar baja')
                ->modalDescription('Solo aparecen miembros en estado ACTIVO o RESERVA y sin otro procedimiento abierto.')
                ->form([
                    $this->userSelect(MemberProcedure::TYPE_DEPARTURE, 'Usuario'),
                    ...ProcedureForumThreadFields::fields(),
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
                ->modalDescription('Solo aparecen miembros en estado ACTIVO o RESERVA y sin otro procedimiento abierto.')
                ->form([
                    $this->userSelect(MemberProcedure::TYPE_DISMISSAL, 'Usuario'),
                    ...ProcedureForumThreadFields::fields(),
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
            if ($type === MemberProcedure::TYPE_RECRUITMENT_START) {
                $input['recruit_nickname'] = trim((string) ($data['recruit_nickname'] ?? ''));
            }
            $postId = ProcedureForumThreadFields::validatedPostId($data);
            if ($postId !== null) {
                $input['forum_post_id'] = $postId;
            }
            $procedure = $engine->start($user, $type, $input, auth()->id());
            // El alta puede haber cambiado el nick antes de sincronizar los pasos.
            $user->refresh();

            Notification::make()
                ->success()
                ->title('Procedimiento iniciado')
                ->body($user->nick . ' · ' . $procedure->typeLabel() . ' · ' . $procedure->statusLabel())
                ->send();

            $this->redirect(MemberProcedureResource::getUrl('edit', ['record' => $procedure]));
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title('Revisa el nickname')
                ->body($exception->validator->errors()->first())
                ->send();
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
