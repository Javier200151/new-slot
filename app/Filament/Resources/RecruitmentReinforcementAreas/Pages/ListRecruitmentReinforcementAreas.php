<?php
namespace App\Filament\Resources\RecruitmentReinforcementAreas\Pages;
use App\Filament\Resources\RecruitmentReinforcementAreas\RecruitmentReinforcementAreaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
class ListRecruitmentReinforcementAreas extends ListRecords { protected static string $resource=RecruitmentReinforcementAreaResource::class; protected function getHeaderActions(): array { return [CreateAction::make()]; } }
