<?php

namespace App\Filament\Resources\PublicNavigationSettings\Pages;

use App\Filament\Resources\PublicNavigationSettings\PublicNavigationSettingResource;
use App\Models\PublicNavigationSetting;
use App\Support\PublicNavigation;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use InvalidArgumentException;

class EditPublicNavigationSetting extends Page
{
    protected static string $resource = PublicNavigationSettingResource::class;

    protected static ?string $title = 'Editor de navegación pública';

    protected string $view =
        'filament.resources.public-navigation-settings.pages.edit-public-navigation-setting';

    public int $recordId;

    /** @var array<int, array<string, mixed>> */
    public array $menuItems = [];

    /** @var array<int, array<string, mixed>> */
    public array $availablePages = [];

    /** @var array<string, array<string, mixed>> */
    public array $destinations = [];

    /** @var array<string, string> */
    public array $audiences = [];

    public int $maxTopLevelItems = PublicNavigation::MAX_TOP_LEVEL_ITEMS;

    public function mount(int|string $record): void
    {
        abort_unless(
            auth()->user()?->can('public-navigation.update') ?? false,
            403,
        );

        $navigation = PublicNavigationSetting::query()->findOrFail($record);

        $this->recordId = (int) $navigation->getKey();
        $this->destinations = PublicNavigation::destinations();
        $this->audiences = PublicNavigation::audienceOptions();

        $state = PublicNavigation::editorState(
            is_array($navigation->items)
                ? $navigation->items
                : PublicNavigation::defaultItems(),
        );

        $this->menuItems = $state['menu'];
        $this->availablePages = $state['available'];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToNavigation')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(PublicNavigationSettingResource::getUrl('index')),
        ];
    }

    /**
     * @param  array<int, mixed>  $menuItems
     * @param  array<int, mixed>  $availablePages
     * @return array{ok: bool, message: string}
     */
    public function saveLayout(array $menuItems, array $availablePages = []): array
    {
        abort_unless(
            auth()->user()?->can('public-navigation.update') ?? false,
            403,
        );

        $navigation = PublicNavigationSetting::query()->findOrFail($this->recordId);

        try {
            $normalized = PublicNavigation::normalizeEditorState(
                $menuItems,
                $availablePages,
            );
        } catch (InvalidArgumentException $exception) {
            return [
                'ok' => false,
                'message' => $exception->getMessage(),
            ];
        }

        $navigation->update([
            'items' => $normalized,
        ]);

        $this->menuItems = PublicNavigation::editorState($normalized)['menu'];

        return [
            'ok' => true,
            'message' => 'Navegación guardada. El frontend ya usa el nuevo orden y visibilidad por estado.',
        ];
    }
}
