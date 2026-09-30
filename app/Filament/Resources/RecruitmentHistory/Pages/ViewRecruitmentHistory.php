<?php
namespace App\Filament\Resources\RecruitmentHistory\Pages;
use App\Filament\Resources\RecruitmentHistory\RecruitmentHistoryResource;
use Filament\Resources\Pages\ViewRecord;
class ViewRecruitmentHistory extends ViewRecord { protected static string $resource=RecruitmentHistoryResource::class; protected function getHeaderActions(): array{return[];} }
