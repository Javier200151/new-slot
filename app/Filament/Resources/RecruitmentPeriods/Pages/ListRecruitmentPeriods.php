<?php

namespace App\Filament\Resources\RecruitmentPeriods\Pages;

use App\Filament\Resources\RecruitmentPeriods\RecruitmentPeriodResource;
use Filament\Resources\Pages\ListRecords;

class ListRecruitmentPeriods extends ListRecords
{
    protected static string $resource = RecruitmentPeriodResource::class;
    protected function getHeaderActions(): array { return []; }
}
