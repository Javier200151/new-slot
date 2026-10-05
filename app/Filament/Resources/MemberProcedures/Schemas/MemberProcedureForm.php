<?php

namespace App\Filament\Resources\MemberProcedures\Schemas;

use App\Models\MemberProcedure;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MemberProcedureForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Procedimiento')
                ->schema([
                    Placeholder::make('member_display')
                        ->label('Miembro')
                        ->content(fn (?MemberProcedure $record): string => $record?->user?->nick ?? '—'),
                    Placeholder::make('type_display')
                        ->label('Tipo')
                        ->content(fn (?MemberProcedure $record): string => $record?->typeLabel() ?? '—'),
                    Placeholder::make('status_display')
                        ->label('Estado')
                        ->content(fn (?MemberProcedure $record): string => $record?->statusLabel() ?? '—'),
                    Placeholder::make('started_display')
                        ->label('Iniciado')
                        ->content(fn (?MemberProcedure $record): string => $record?->started_at?->format('d/m/Y H:i') ?? '—'),
                    Placeholder::make('actor_display')
                        ->label('Iniciado por')
                        ->content(fn (?MemberProcedure $record): string => $record?->startedBy?->nick ?? 'Sistema'),
                    Placeholder::make('input_display')
                        ->label('Datos del procedimiento')
                        ->content(function (?MemberProcedure $record): string {
                            if (! $record || empty($record->input)) {
                                return 'Sin datos adicionales.';
                            }

                            $parts = [];
                            foreach ($record->input as $key => $value) {
                                if ($key === 'promo_id') {
                                    $parts[] = 'Promoción ID: ' . $value;
                                } elseif ($key === 'reason') {
                                    $parts[] = 'Motivo: ' . $value;
                                } elseif ($key === 'ban_required') {
                                    $parts[] = 'Bloqueo/ban indicado: ' . ($value ? 'Sí' : 'No');
                                }
                            }

                            return $parts !== [] ? implode(' · ', $parts) : 'Sin datos adicionales.';
                        })
                        ->columnSpanFull(),
                ])
                ->columns(3),
        ]);
    }
}
