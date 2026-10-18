<?php

namespace App\Filament\Pages;

use App\Models\PublicFooterSetting;
use App\Support\PublicFooterNavigation;
use App\Support\PublicNavigation;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use UnitEnum;

class EditPublicFooter extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Sistema';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;
    protected static ?string $navigationLabel = 'Footer público';
    protected static ?int $navigationSort = 2;
    protected static ?string $title = 'Editor del footer público';
    protected string $view = 'filament.pages.edit-public-footer';

    public array $items = [];
    public array $catalog = [];
    public array $audiences = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('public-navigation.update') ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->items = PublicFooterNavigation::editorItems(PublicFooterNavigation::items());
        $this->catalog = PublicFooterNavigation::catalog();
        $this->audiences = PublicNavigation::audienceOptions();
    }

    public function saveLayout(array $items): array
    {
        abort_unless(static::canAccess(), 403);

        try {
            $normalized = PublicFooterNavigation::validateAndNormalize($items);
        } catch (InvalidArgumentException $exception) {
            return ['ok' => false, 'message' => $exception->getMessage()];
        }

        DB::transaction(function () use ($normalized): void {
            $setting = PublicFooterSetting::query()->lockForUpdate()->first();
            if ($setting) {
                $setting->update(['items' => $normalized]);
            } else {
                PublicFooterSetting::query()->create(['items' => $normalized]);
            }
        });

        return ['ok' => true, 'message' => 'Footer actualizado. El header conserva su configuración independiente.'];
    }
}
