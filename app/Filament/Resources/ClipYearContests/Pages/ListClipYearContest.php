<?php
namespace App\Filament\Resources\ClipYearContests\Pages;
use App\Filament\Resources\ClipYearContests\ClipYearContestResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\CreateAction;
class ListClipYearContest extends ListRecords
{
    protected static string $resource = ClipYearContestResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()]; }
}
