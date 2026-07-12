<?php

namespace App\Filament\Resources\SlotTypes\RelationManagers;

use App\Models\Status;
use Filament\Actions\Action;
use Filament\Actions\DetachAction;
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
                        $this->getOwnerRecord()
                            ->statuses()
                            ->syncWithoutDetaching($data['status_ids'] ?? []);
                    }),
            ])
            ->recordActions([
                DetachAction::make()
                    ->label('Quitar'),
            ]);
    }
}
