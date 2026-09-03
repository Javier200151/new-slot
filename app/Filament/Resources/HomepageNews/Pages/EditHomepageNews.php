<?php
namespace App\Filament\Resources\HomepageNews\Pages;
use App\Filament\Resources\HomepageNews\HomepageNewsResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
class EditHomepageNews extends EditRecord { protected static string $resource = HomepageNewsResource::class; protected function getHeaderActions(): array { return [DeleteAction::make()]; } }
