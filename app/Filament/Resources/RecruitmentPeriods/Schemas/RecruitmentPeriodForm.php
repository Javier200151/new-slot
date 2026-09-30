<?php

namespace App\Filament\Resources\RecruitmentPeriods\Schemas;

use App\Models\RecruitmentPeriod;
use App\Models\RecruitmentReinforcementArea;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RecruitmentPeriodForm
{
    public static function configure(Schema $schema, bool $readOnly = false): Schema
    {
        return $schema->components([
            Section::make('Recluta')
                ->schema([
                    Placeholder::make('recruit_name')->label('Recluta')->content(fn (?RecruitmentPeriod $record): string => $record?->user?->nick ?? '—'),
                    TextInput::make('period_number')->label('Periodo')->disabled(),
                    DateTimePicker::make('started_at')
                        ->label('Fecha de ingreso como recluta')
                        ->seconds(false)
                        ->displayFormat('d/m/Y H:i')
                        ->disabled($readOnly)
                        ->helperText('Se coloca automáticamente al pasar a RECLUTA. Mientras el periodo esté abierto puede corregirse manualmente.'),
                    Select::make('tutor_id')
                        ->label('Tutor')
                        ->options(fn (): array => User::query()
                            ->where(function ($query): void {
                                $query->whereHas('permissions', fn ($permissions) => $permissions->where('name', 'recruitment-area.access'))
                                    ->orWhereHas('roles.permissions', fn ($permissions) => $permissions->where('name', 'recruitment-area.access'));
                            })
                            ->orderBy('nick')
                            ->pluck('nick', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->disabled(fn (): bool => $readOnly || ! auth()->user()?->getAllPermissions()->contains('name', 'recruitment-area.assign')),
                    Select::make('process_status')
                        ->label('Estado del proceso')
                        ->options([
                            RecruitmentPeriod::PROCESS_PENDING_TUTOR => 'Pendiente de tutor',
                            RecruitmentPeriod::PROCESS_IN_PROGRESS => 'En curso',
                            RecruitmentPeriod::PROCESS_PENDING_PROMOTION => 'Pendiente de promocionar',
                            RecruitmentPeriod::PROCESS_CLOSED => 'Cerrado',
                        ])
                        ->disabled(),
                    Placeholder::make('promotion_pending_trace')
                        ->label('Pendiente de promoción')
                        ->content(fn (?RecruitmentPeriod $record): string => $record?->promotion_pending_at
                            ? $record->promotion_pending_at->format('d/m/Y H:i') . ' · ' . ($record->promotionPendingBy?->nick ?? 'Sistema')
                            : 'No marcado'),
                    Textarea::make('current_note')
                        ->label('Nota / disponibilidad')
                        ->rows(3)
                        ->disabled($readOnly)
                        ->columnSpanFull(),
                ])->columns(2),

            Section::make('Seguimiento')
                ->schema([
                    Select::make('tutorials_status')
                        ->label('Tutorías')
                        ->options([
                            RecruitmentPeriod::TUTORIALS_NO => 'No',
                            RecruitmentPeriod::TUTORIALS_PARTIAL => 'Parcial',
                            RecruitmentPeriod::TUTORIALS_YES => 'Sí',
                        ])
                        ->required()
                        ->disabled($readOnly),
                    Select::make('diary_rating')
                        ->label('Valoración de diarios y debriefings')
                        ->options([
                            RecruitmentPeriod::DIARY_VERY_GOOD => 'Muy bueno',
                            RecruitmentPeriod::DIARY_GOOD => 'Bueno',
                            RecruitmentPeriod::DIARY_IMPROVABLE => 'Mejorable',
                            RecruitmentPeriod::DIARY_DEFICIENT => 'Deficiente',
                        ])
                        ->nullable()
                        ->disabled($readOnly),
                    Toggle::make('official_events_allowed')
                        ->label('Permitir apuntarse a partidas oficiales')
                        ->helperText('Si está desactivado, el recluta no podrá apuntarse a las partidas oficiales de martes y viernes.')
                        ->disabled($readOnly),
                    Select::make('reinforcementAreas')
                        ->label('Refuerzo necesario')
                        ->relationship('reinforcementAreas', 'name')
                        ->multiple()
                        ->options(fn (?RecruitmentPeriod $record): array => RecruitmentReinforcementArea::query()
                            ->where(function ($query) use ($record): void {
                                $query->where('active', true);
                                if ($record) {
                                    $query->orWhereIn('id', $record->reinforcementAreas()->pluck('recruitment_reinforcement_areas.id'));
                                }
                            })
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->disabled($readOnly),
                ])->columns(2),
        ]);
    }
}
