<?php
namespace App\Filament\Resources\RecruitmentReinforcementAreas\Pages;
use App\Filament\Resources\RecruitmentReinforcementAreas\RecruitmentReinforcementAreaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
class EditRecruitmentReinforcementArea extends EditRecord { protected static string $resource=RecruitmentReinforcementAreaResource::class; protected function getHeaderActions(): array { return [DeleteAction::make()->disabled(fn()=> $this->record->deletionBlockReason()!==null)->tooltip(fn()=> $this->record->deletionBlockReason())]; } }
