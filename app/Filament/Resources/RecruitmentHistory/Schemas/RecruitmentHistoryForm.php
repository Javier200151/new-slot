<?php

namespace App\Filament\Resources\RecruitmentHistory\Schemas;

use App\Models\RecruitmentPeriod;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RecruitmentHistoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Periodo histórico')
                ->schema([
                    TextInput::make('user_nick_snapshot')->label('Recluta')->disabled(),
                    TextInput::make('period_number')->label('Periodo')->disabled(),
                    DateTimePicker::make('started_at')->label('Inicio')->displayFormat('d/m/Y H:i')->disabled(),
                    DateTimePicker::make('ended_at')->label('Fin')->displayFormat('d/m/Y H:i')->disabled(),
                    TextInput::make('tutor_nick_snapshot')->label('Tutor')->disabled(),
                    Select::make('result')
                        ->label('Resultado')
                        ->options([
                            RecruitmentPeriod::RESULT_PROMOTED => 'PROMOCIONADO',
                            RecruitmentPeriod::RESULT_NOT_PROMOTED => 'NO PROMOCIONADO',
                        ])
                        ->disabled(),
                    TextInput::make('final_status_name')->label('Estado final')->disabled(),
                    TextInput::make('events_played_final')->label('Eventos jugados')->disabled(),
                ])->columns(2),

            Section::make('Seguimiento congelado')
                ->schema([
                    Select::make('tutorials_status')
                        ->label('Tutorías')
                        ->options([
                            RecruitmentPeriod::TUTORIALS_NO => 'No',
                            RecruitmentPeriod::TUTORIALS_PARTIAL => 'Parcial',
                            RecruitmentPeriod::TUTORIALS_YES => 'Sí',
                        ])->disabled(),
                    Select::make('diary_rating')
                        ->label('Valoración de diarios y debriefings')
                        ->options([
                            RecruitmentPeriod::DIARY_VERY_GOOD => 'Muy bueno',
                            RecruitmentPeriod::DIARY_GOOD => 'Bueno',
                            RecruitmentPeriod::DIARY_IMPROVABLE => 'Mejorable',
                            RecruitmentPeriod::DIARY_DEFICIENT => 'Deficiente',
                        ])->disabled(),
                    Toggle::make('official_events_allowed')
                        ->label('Permitía partidas oficiales')
                        ->disabled(),
                    Select::make('reinforcementAreas')
                        ->label('Refuerzos')
                        ->relationship('reinforcementAreas', 'name')
                        ->multiple()
                        ->disabled(),
                    Textarea::make('current_note')
                        ->label('Nota / disponibilidad al cierre')
                        ->rows(3)
                        ->disabled()
                        ->columnSpanFull(),
                    DateTimePicker::make('promotion_pending_at')
                        ->label('Marcado pendiente de promoción')
                        ->displayFormat('d/m/Y H:i')
                        ->disabled(),
                    TextInput::make('promotionPendingBy.nick')
                        ->label('Promoción marcada por')
                        ->disabled(),
                    DateTimePicker::make('dismissal_pending_at')
                        ->label('Marcado pendiente de baja')
                        ->displayFormat('d/m/Y H:i')
                        ->disabled(),
                    TextInput::make('dismissalPendingBy.nick')
                        ->label('Baja marcada por')
                        ->disabled(),
                ])->columns(2),
        ]);
    }
}
