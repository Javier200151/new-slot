<?php

namespace App\Filament\Resources\SlotTypes\RelationManagers;

use App\Models\Status;
use Filament\Actions\Action;
use Filament\Actions\DetachAction;
use App\Models\SlotTypeStatus;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StatusesRelationManager extends RelationManager
{
    protected static string $relationship = 'statuses';

    protected static ?string $title = 'Estados asociados';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Estado')
                    ->searchable()
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('assignStatuses')
                    ->label('Añadir estados')
                    ->form([
                        Select::make('status_ids')
                            ->label('Estados')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(fn (): array => Status::query()
                                ->whereNotIn('id', $this->getOwnerRecord()->statuses()->pluck('status.id'))
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $slotTypeId =
                            (int) $this
                                ->getOwnerRecord()
                                ->getKey();

                        $statusIds = collect(
                            $data['status_ids'] ?? []
                        )
                            ->map(
                                fn ($statusId): int =>
                                    (int) $statusId
                            )
                            ->unique()
                            ->values();

                        foreach ($statusIds as $statusId) {
                            SlotTypeStatus::query()
                                ->firstOrCreate([
                                    'slot_type_id' =>
                                        $slotTypeId,

                                    'status_id' =>
                                        $statusId,
                                ]);
                        }
                    }),
            ])
            ->recordActions([
                Action::make('removeStatus')
                    ->label('Quitar')
                    ->color('danger')
                    ->icon(
                        'heroicon-o-link-slash'
                    )
                    ->requiresConfirmation()
                    ->modalHeading(
                        'Quitar estado'
                    )
                    ->modalDescription(
                        'Se eliminará la asociación '
                        . 'entre este tipo de slot '
                        . 'y el estado seleccionado.'
                    )
                    ->action(
                        function (
                            Status $record
                        ): void {
                            $slotTypeId =
                                (int) $this
                                    ->getOwnerRecord()
                                    ->getKey();

                            /*
                            * Usamos los modelos reales
                            * en lugar de delete() masivo
                            * para que Auditable reciba
                            * el evento deleted.
                            */
                            SlotTypeStatus::query()
                                ->where(
                                    'slot_type_id',
                                    $slotTypeId
                                )
                                ->where(
                                    'status_id',
                                    $record->getKey()
                                )
                                ->get()
                                ->each(
                                    fn (
                                        SlotTypeStatus $relation
                                    ) =>
                                        $relation->delete()
                                );
                        }
                    ),
            ]);
    }
}
