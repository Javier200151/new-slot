<?php
namespace App\Filament\Resources\RecruitmentReentryReviews\Tables;
use App\Models\RecruitmentReentryReview;
use App\Services\RecruitmentPeriodService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
class RecruitmentReentryReviewsTable { public static function configure(Table $table):Table{return$table->columns([
 TextColumn::make('user.nick')->label('Usuario')->searchable(),
 TextColumn::make('previousPeriod.period_number')->label('Último periodo'),
 TextColumn::make('previousPeriod.ended_at')->label('Fin anterior')->dateTime('d/m/Y H:i'),
 TextColumn::make('detected_at')->label('Detectado')->dateTime('d/m/Y H:i')->sortable(),
 TextColumn::make('warning')->label('Motivo')->state('Ya completó anteriormente un periodo PROMOCIONADO. Confirmar si debe repetir reclutamiento.')->wrap(),
])->recordActions([
 Action::make('start')->label('Iniciar nuevo periodo')->color('success')->icon('heroicon-o-plus-circle')->requiresConfirmation()->action(function(RecruitmentReentryReview $record):void{app(RecruitmentPeriodService::class)->confirmPromotedReentry($record,auth()->id());Notification::make()->success()->title('Nuevo periodo iniciado')->send();}),
 Action::make('error')->label('Cambio de status por error')->color('gray')->icon('heroicon-o-x-circle')->requiresConfirmation()->action(function(RecruitmentReentryReview $record):void{app(RecruitmentPeriodService::class)->resolvePromotedReentryAsStatusError($record,auth()->id());Notification::make()->success()->title('Reincorporación descartada')->send();}),
]);} }
