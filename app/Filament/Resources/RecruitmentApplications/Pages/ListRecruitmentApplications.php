<?php

namespace App\Filament\Resources\RecruitmentApplications\Pages;

use App\Filament\Resources\RecruitmentApplications\RecruitmentApplicationResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListRecruitmentApplications extends ListRecords
{
    protected static string $resource = RecruitmentApplicationResource::class;

    public function getTitle(): string
    {
        return 'Gestión de alistados';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('boardView')
                ->label('Ver tablero')
                ->icon('heroicon-o-view-columns')
                ->url(RecruitmentApplicationResource::getUrl('index')),
        ];
    }
}
