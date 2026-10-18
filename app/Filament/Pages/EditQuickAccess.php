<?php

namespace App\Filament\Pages;

use App\Models\PublicQuickAccessSetting;
use App\Support\PublicQuickAccessNavigation;
use App\Support\PublicNavigation;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use UnitEnum;

class EditQuickAccess extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Sistema';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;
    protected static ?string $navigationLabel = 'Accesos rápidos';
    protected static ?int $navigationSort = 3;
    protected static ?string $title = 'Editor de accesos rápidos';
    protected string $view = 'filament.pages.edit-quick-access';

    public array $items = [];
    public array $catalog = [];
    public array $icons = [];
    public array $audiences = [];
    public string $position = 'right';
    public bool $isEnabled = true;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('public-navigation.update') ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $settings = PublicQuickAccessNavigation::settings();
        $this->items = PublicQuickAccessNavigation::editorItems($settings['items']);
        $this->position = $settings['position'];
        $this->isEnabled = $settings['is_enabled'];
        $this->catalog = PublicQuickAccessNavigation::catalog();
        $this->audiences = PublicNavigation::audienceOptions();
        $this->icons = PublicQuickAccessNavigation::icons();
    }

    public function saveLayout(array $items, string $position, bool $enabled): array
    {
        abort_unless(static::canAccess(), 403);
        try {
            if (! in_array($position, ['left', 'right'], true)) {
                throw new InvalidArgumentException('El lateral debe ser izquierdo o derecho.');
            }
            $normalized = PublicQuickAccessNavigation::validateAndNormalize($items);
        } catch (InvalidArgumentException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        DB::transaction(function () use ($normalized, $position, $enabled): void {
            $setting = PublicQuickAccessSetting::query()->lockForUpdate()->first();
            $attributes = ['items' => $normalized, 'position' => $position, 'is_enabled' => $enabled];
            if ($setting) {
                $setting->update($attributes);
            } else {
                PublicQuickAccessSetting::query()->create($attributes);
            }
        });

        return ['ok' => true, 'message' => 'Accesos rápidos guardados. Header y footer no se han modificado.'];
    }
}
