<?php

namespace App\Filament\Pages;

use App\Models\Metopa;
use App\Models\User;
use App\Models\UserMetopa;
use App\Services\UserMetopaAssignmentService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;

class ManageUserMetopas extends Page implements
    HasActions,
    HasSchemas,
    HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedTrophy;

    protected static ?string $navigationLabel = 'Gestión de metopas';

    protected static ?string $title = 'Gestión de metopas';

    protected static string|UnitEnum|null $navigationGroup = 'Usuarios';

    protected static ?int $navigationSort = 3;

    protected string $view =
        'filament.pages.manage-user-metopas';

    public static function canAccess(): bool
    {
        return Auth::user()?->can('user-metopas.view') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                UserMetopa::query()
                    ->with([
                        'user',
                        'metopa',
                        'metopa.sqaGroup',
                        'createdBy',
                        'updatedBy',
                    ])
            )
            ->columns([
                TextColumn::make('user.nick')
                    ->label('Usuario')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('metopa.name')
                    ->label('Metopa')
                    ->searchable()
                    ->sortable(),

                ImageColumn::make('metopa.image')
                    ->label('Imagen')
                    ->state(
                        fn (UserMetopa $record): ?string =>
                            $record->metopa?->image
                                ? url(
                                    'storage/'
                                    . $record->metopa->image
                                )
                                : null
                    )
                    ->imageWidth(86)
                    ->imageHeight(25),

                TextColumn::make('metopa.sqaGroup.name')
                    ->label('Grupo SQA')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('assigned_at')
                    ->label('Fecha de asignación')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('createdBy.nick')
                    ->label('Creado por')
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                TextColumn::make('updatedBy.nick')
                    ->label('Actualizado por')
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                TextColumn::make('updated_at')
                    ->label('Última modificación')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('Usuario')
                    ->options(
                        User::query()
                            ->orderBy('nick')
                            ->pluck('nick', 'id')
                    )
                    ->searchable()
                    ->preload(),

                SelectFilter::make('metopa_id')
                    ->label('Metopa')
                    ->options(
                        Metopa::query()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                    )
                    ->searchable()
                    ->preload(),
            ])
            ->defaultKeySort(false)
            ->headerActions([
                $this->assignMetopasAction(),
            ])
            ->recordActions([
                $this->editAssignmentDateAction(),
                $this->deleteAssignmentAction(),
            ]);
    }

    private function assignMetopasAction(): Action
    {
        return Action::make('assignMetopas')
            ->label('Asignar metopas')
            ->icon('heroicon-o-trophy')
            ->visible(
                fn (): bool =>
                    Auth::user()?->can('user-metopas.create') ?? false
            )
            ->form([
                Select::make('user_ids')
                    ->label('Usuarios')
                    ->multiple()
                    ->options(
                        User::query()
                            ->orderBy('nick')
                            ->pluck('nick', 'id')
                    )
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('metopa_ids')
                    ->label('Metopas')
                    ->multiple()
                    ->options(
                        Metopa::query()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                    )
                    ->searchable()
                    ->preload()
                    ->required(),

                DateTimePicker::make('assigned_at')
                    ->label('Fecha y hora de asignación')
                    ->displayFormat('d/m/Y H:i')
                    ->seconds(false)
                    ->default(now())
                    ->required(),

                Checkbox::make('update_existing')
                    ->label(
                        'Actualizar también la fecha de las asignaciones existentes'
                    ),
            ])
            ->modalHeading('Asignar metopas a usuarios')
            ->modalSubmitActionLabel('Asignar')
            ->action(function (
                array $data,
                UserMetopaAssignmentService $service,
            ): void {
                abort_unless(
                    Auth::user()?->can('user-metopas.create'),
                    403
                );

                $results = $this->emptyResults();

                foreach ($data['user_ids'] as $userId) {
                    foreach ($data['metopa_ids'] as $metopaId) {
                        $result = $service->assign(
                            userId: (int) $userId,
                            metopaId: (int) $metopaId,
                            assignedAt: $data['assigned_at'],
                            updateExisting:
                                (bool) ($data['update_existing'] ?? false),
                        );

                        $results[$result]++;
                    }
                }

                $this->sendAssignmentNotification($results);
            });
    }

    private function editAssignmentDateAction(): Action
    {
        return Action::make('editAssignedAt')
            ->label('Editar fecha')
            ->icon('heroicon-o-calendar-days')
            ->visible(
                fn (): bool =>
                    Auth::user()?->can(
                        'user-metopas.update'
                    ) ?? false
            )
            ->fillForm(
                fn (UserMetopa $record): array => [
                    'assigned_at' => $record->assigned_at,
                ]
            )
            ->form([
                DateTimePicker::make('assigned_at')
                    ->label('Fecha y hora de asignación')
                    ->displayFormat('d/m/Y H:i')
                    ->seconds(false)
                    ->required(),
            ])
            ->action(function (
                UserMetopa $record,
                array $data,
                UserMetopaAssignmentService $service,
            ): void {
                abort_unless(
                    Auth::user()?->can(
                        'user-metopas.update'
                    ),
                    403
                );

                $service->updateAssignedAt(
                    userId: (int) $record->user_id,
                    metopaId: (int) $record->metopa_id,
                    assignedAt: $data['assigned_at'],
                );

                Notification::make()
                    ->title('Fecha actualizada')
                    ->success()
                    ->send();
            });
    }

    private function deleteAssignmentAction(): Action
    {
        return Action::make('deleteAssignment')
            ->label('Quitar')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Quitar metopa del usuario')
            ->modalDescription(
                'La asignación se eliminará de forma reversible. '
                . 'Si se vuelve a asignar, se restaurará automáticamente.'
            )
            ->visible(
                fn (): bool =>
                    Auth::user()?->can(
                        'user-metopas.delete'
                    ) ?? false
            )
            ->action(function (
                UserMetopa $record,
                UserMetopaAssignmentService $service,
            ): void {
                abort_unless(
                    Auth::user()?->can(
                        'user-metopas.delete'
                    ),
                    403
                );

                $service->delete(
                    userId: (int) $record->user_id,
                    metopaId: (int) $record->metopa_id,
                );

                Notification::make()
                    ->title('Asignación eliminada')
                    ->success()
                    ->send();
            });
    }

    private function emptyResults(): array
    {
        return [
            'created' => 0,
            'restored' => 0,
            'updated' => 0,
            'already_exists' => 0,
        ];
    }

    private function sendAssignmentNotification(
        array $results,
    ): void {
        $lines = [];

        if ($results['created'] > 0) {
            $lines[] = "{$results['created']} creadas";
        }

        if ($results['restored'] > 0) {
            $lines[] = "{$results['restored']} restauradas";
        }

        if ($results['updated'] > 0) {
            $lines[] = "{$results['updated']} actualizadas";
        }

        if ($results['already_exists'] > 0) {
            $lines[] =
                "{$results['already_exists']} ya existían";
        }

        Notification::make()
            ->title('Asignaciones procesadas')
            ->body(implode(' · ', $lines))
            ->success()
            ->send();
    }
}