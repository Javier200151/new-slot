<?php
namespace App\Filament\Resources\HomepageNews\Pages;
use App\Filament\Resources\HomepageNews\HomepageNewsResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
class ListHomepageNews extends ListRecords { protected static string $resource = HomepageNewsResource::class; protected function getHeaderActions(): array { return [CreateAction::make()]; } }
