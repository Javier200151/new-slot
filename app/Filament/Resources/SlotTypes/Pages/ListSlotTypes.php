<?php

namespace App\Filament\Resources\SlotTypes\Pages;

use App\Filament\Resources\SlotTypes\SlotTypeResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSlotTypes extends ListRecords
{
    protected static string $resource = SlotTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('previewPicker')
                ->label('Preview / ordenar selector')
                ->icon('heroicon-o-squares-2x2')
                ->color('gray')
                ->url(SlotTypeResource::getUrl('preview'))
                ->visible(
                    fn (): bool =>
                        auth()->user()?->can('slot-types.update') ?? false
                ),

            CreateAction::make(),
        ];
    }
}
