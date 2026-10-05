<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\MemberProcedures\MemberProcedureResource;
use App\Models\MemberProcedure;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MemberProceduresRelationManager extends RelationManager
{
    protected static string $relationship = 'memberProcedures';
    protected static ?string $title = 'Procedimientos';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->can('member-procedures.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('Procedimiento')
                    ->formatStateUsing(fn (MemberProcedure $record): string => $record->typeLabel())
                    ->badge(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn (MemberProcedure $record): string => $record->statusLabel())
                    ->badge(),
                TextColumn::make('progress')
                    ->label('Progreso')
                    ->state(function (MemberProcedure $record): string {
                        $total = $record->steps()->where('required', true)->count();
                        $done = $record->steps()->where('required', true)->whereIn('status', ['completed', 'skipped'])->count();

                        return $done . ' / ' . $total;
                    }),
                TextColumn::make('started_at')->label('Inicio')->dateTime('d/m/Y H:i'),
                TextColumn::make('completed_at')->label('Fin')->dateTime('d/m/Y H:i')->placeholder('—'),
            ])
            ->defaultSort('started_at', 'desc')
            ->headerActions([])
            ->recordActions([
                Action::make('open')
                    ->label('Abrir')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (MemberProcedure $record): string => MemberProcedureResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
