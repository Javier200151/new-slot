<?php

namespace App\Filament\Resources\Streamers\Pages;

use App\Filament\Resources\Streamers\StreamerResource;
use App\Models\TwitchCategorySetting;
use App\Services\TwitchArmaCategoryService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListStreamers extends ListRecords
{
    protected static string $resource = StreamerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('twitchCategories')
                ->label('Categorías permitidas de Twitch')
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->visible(fn (): bool => (bool) auth()->user()?->hasRole('admin'))
                ->modalHeading('Categorías permitidas de Twitch')
                ->modalDescription('Selecciona qué contenidos de Twitch pueden aparecer en la web. YouTube no se verá afectado.')
                ->modalSubmitActionLabel('Guardar categorías')
                ->schema([
                    Select::make('allowed_categories')
                        ->label('Categorías permitidas')
                        ->multiple()
                        ->searchable()
                        ->searchPrompt('Escribe el nombre de un juego o categoría de Twitch')
                        ->getSearchResultsUsing(fn (string $search): array => app(TwitchArmaCategoryService::class)->searchCategories($search))
                        ->getOptionLabelsUsing(fn (array $values): array => collect($values)
                            ->filter(fn ($name): bool => is_string($name) && $name !== '')
                            ->mapWithKeys(fn (string $name): array => [$name => $name])
                            ->all())
                        ->helperText('Empieza escribiendo al menos dos letras. Si no seleccionas ninguna, no se mostrarán directos ni vídeos de Twitch.')
                        ->nullable(),
                ])
                ->fillForm(fn (): array => [
                    'allowed_categories' => TwitchCategorySetting::current()->allowed_categories ?? [],
                ])
                ->action(function (array $data): void {
                    $names = collect($data['allowed_categories'] ?? [])
                        ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
                        ->map(fn (string $value): string => trim($value))
                        ->unique(fn (string $value): string => mb_strtolower($value, 'UTF-8'))
                        ->values()
                        ->all();

                    if (count($names) > 25 || ! app(TwitchArmaCategoryService::class)->verifyNames($names)) {
                        Notification::make()
                            ->title('No se pudieron comprobar las categorías en Twitch')
                            ->body('Comprueba los nombres o inténtalo de nuevo. No se ha cambiado la configuración.')
                            ->danger()
                            ->send();
                        return;
                    }

                    $setting = TwitchCategorySetting::current();
                    $setting->allowed_categories = $names;
                    $setting->save();

                    Notification::make()->title('Categorías de Twitch guardadas')->success()->send();
                }),
            CreateAction::make(),
        ];
    }
}
