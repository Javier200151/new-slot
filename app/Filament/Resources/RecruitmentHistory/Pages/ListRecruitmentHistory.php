<?php
namespace App\Filament\Resources\RecruitmentHistory\Pages;
use App\Filament\Resources\RecruitmentHistory\RecruitmentHistoryResource;
use Filament\Resources\Pages\ListRecords;
class ListRecruitmentHistory extends ListRecords { protected static string $resource=RecruitmentHistoryResource::class; protected function getHeaderActions(): array{return[];} }
