<?php
namespace App\Filament\Resources\HomepageSettings\Pages;
use App\Filament\Resources\HomepageSettings\HomepageSettingResource;
use App\Models\HomepageSetting;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
class ListHomepageSettings extends ListRecords { protected static string $resource = HomepageSettingResource::class; protected function getHeaderActions(): array { return HomepageSetting::query()->exists() ? [] : [CreateAction::make()]; } }
