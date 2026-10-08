<?php

namespace App\Filament\Resources\TreasurySettings\Tables;

use App\Models\MemberProcedureSetting;
use App\Models\Status;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TreasurySettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('treasury_spreadsheet_id')
                    ->label('Google Sheet')
                    ->limit(28)
                    ->tooltip(fn (MemberProcedureSetting $record): ?string => $record->treasury_spreadsheet_id),

                TextColumn::make('treasuryGroup.name')
                    ->label('Grupo Tesorería')
                    ->default('Sin configurar'),

                TextColumn::make('private_statuses')
                    ->label('Mi saldo visible para')
                    ->state(function (MemberProcedureSetting $record): string {
                        $ids = collect($record->treasury_private_status_ids ?? [])
                            ->map(fn ($id): int => (int) $id)
                            ->filter()
                            ->values();

                        if ($ids->isEmpty()) {
                            return 'Ningún estado';
                        }

                        return Status::query()
                            ->whereIn('id', $ids->all())
                            ->orderBy('name')
                            ->pluck('name')
                            ->implode(', ');
                    })
                    ->wrap(),

                TextColumn::make('page_statuses')
                    ->label('/tesoreria visible para')
                    ->state(function (MemberProcedureSetting $record): string {
                        $ids = collect($record->treasury_page_status_ids ?? [])
                            ->map(fn ($id): int => (int) $id)
                            ->filter()
                            ->values();

                        if ($ids->isEmpty()) {
                            return 'Ningún estado';
                        }

                        return Status::query()
                            ->whereIn('id', $ids->all())
                            ->orderBy('name')
                            ->pluck('name')
                            ->implode(', ');
                    })
                    ->wrap(),

                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->since(),
            ])
            ->paginated(false)
            ->recordActions([
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()?->can('treasury-settings.update') ?? false),
            ]);
    }
}
