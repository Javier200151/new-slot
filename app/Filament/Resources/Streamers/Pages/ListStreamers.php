<?php

namespace App\Filament\Resources\Streamers\Pages;

use App\Filament\Resources\Streamers\StreamerResource;
use App\Models\TwitchCategorySetting;
use App\Services\TwitchArmaCategoryService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListStreamers extends ListRecords
{
    protected static string $resource = StreamerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('twitchCategories')
                ->label('Filtros de Twitch')
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->visible(fn (): bool => (bool) auth()->user()?->hasRole('admin'))
                ->modalHeading('Contenido permitido en Twitch')
                ->modalDescription('El contenido de Twitch se acepta si coincide con una categoría, una palabra del título o el horario de un evento. Las emisiones manuales y YouTube no tienen restricciones.')
                ->modalSubmitActionLabel('Guardar filtros')
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
                        ->helperText('Selecciona las categorías de Twitch que quieras permitir.')
                        ->nullable(),
                    Textarea::make('title_keywords_text')
                        ->label('Palabras o frases permitidas en el título')
                        ->rows(5)
                        ->placeholder("arma 3\narma reforger\noperativo")
                        ->helperText('Escribe una palabra o frase por línea. Con que aparezca una en el título, se permite el directo o vídeo.'),
                ])
                ->fillForm(fn (): array => [
                    'allowed_categories' => TwitchCategorySetting::current()->allowed_categories ?? [],
                    'title_keywords_text' => implode("\n", TwitchCategorySetting::current()->title_keywords ?? []),
                ])
                ->action(function (array $data): void {
                    $names = collect($data['allowed_categories'] ?? [])
                        ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
                        ->map(fn (string $value): string => trim($value))
                        ->unique(fn (string $value): string => mb_strtolower($value, 'UTF-8'))
                        ->values()
                        ->all();

                    // Las categorías seleccionadas proceden del buscador oficial de Twitch.
                    // No volver a verificarlas en cada guardado: un fallo temporal de
                    // Twitch no debe bloquear cambios independientes (p. ej. palabras clave)
                    // ni hacer que las categorías ya guardadas parezcan inválidas.
                    if (count($names) > 25 || collect($names)->contains(
                        fn (string $name): bool => mb_strlen($name, 'UTF-8') > 100
                    )) {
                        Notification::make()
                            ->title('Puedes seleccionar hasta 25 categorías')
                            ->danger()
                            ->send();
                        return;
                    }

                    $keywords = collect(preg_split('/\R/u', (string) ($data['title_keywords_text'] ?? '')) ?: [])
                        ->map(fn (string $word): string => trim($word))
                        ->filter()
                        ->unique(fn (string $word): string => mb_strtolower($word, 'UTF-8'))
                        ->values()
                        ->all();

                    if (count($keywords) > 50 || collect($keywords)->contains(fn (string $word): bool => mb_strlen($word, 'UTF-8') > 100)) {
                        Notification::make()->title('Máximo 50 palabras o frases de hasta 100 caracteres')->danger()->send();
                        return;
                    }

                    $setting = TwitchCategorySetting::current();
                    $setting->title_keywords = $keywords;
                    $setting->allowed_categories = $names;
                    $setting->save();

                    Notification::make()->title('Filtros de Twitch guardados')->success()->send();
                }),
            CreateAction::make(),
        ];
    }
}
