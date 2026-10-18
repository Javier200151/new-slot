<?php

namespace Tests\Unit;

use App\Support\PublicQuickAccessNavigation;
use InvalidArgumentException;
use Tests\TestCase;

class PublicQuickAccessNavigationTest extends TestCase
{
    public function test_default_bar_has_icons_and_independent_links(): void
    {
        $items = PublicQuickAccessNavigation::defaultItems();
        $this->assertSame('users', $items[0]['destination']);
        $this->assertSame('scroll_top', $items[3]['type']);
        $this->assertContains($items[0]['icon'], PublicQuickAccessNavigation::icons());
    }

    public function test_all_installed_outline_heroicons_are_available(): void
    {
        $this->assertGreaterThanOrEqual(320, count(PublicQuickAccessNavigation::icons()));
        $this->assertContains('calendar-days', PublicQuickAccessNavigation::icons());
        $this->assertContains('users', PublicQuickAccessNavigation::icons());
    }

    public function test_duplicate_destinations_are_allowed(): void
    {
        $normalized = PublicQuickAccessNavigation::validateAndNormalize([
            ['type' => 'link', 'destination' => 'users', 'label' => 'Usuarios', 'icon' => 'users'],
            ['type' => 'link', 'destination' => 'users', 'label' => 'Comunidad', 'icon' => 'user-group'],
        ]);
        $this->assertCount(2, $normalized);
    }

    public function test_invalid_icon_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PublicQuickAccessNavigation::validateAndNormalize([
            ['type' => 'link', 'destination' => 'users', 'label' => 'Usuarios', 'icon' => '../../other'],
        ]);
    }

    public function test_unsafe_external_url_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PublicQuickAccessNavigation::validateAndNormalize([
            ['type' => 'external', 'label' => 'Peligroso', 'url' => 'javascript:alert(1)', 'icon' => 'link'],
        ]);
    }

    public function test_only_one_scroll_to_top_button_is_allowed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PublicQuickAccessNavigation::validateAndNormalize([
            ['type' => 'scroll_top', 'label' => 'Arriba', 'icon' => 'arrow-up'],
            ['type' => 'scroll_top', 'label' => 'Arriba 2', 'icon' => 'arrow-up'],
        ]);
    }

    public function test_visibility_must_contain_valid_audience(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PublicQuickAccessNavigation::validateAndNormalize([
            ['type' => 'link', 'destination' => 'users', 'label' => 'Usuarios', 'icon' => 'users',
                'visible_to' => ['ANYONE']],
        ]);
    }

    public function test_no_dropdown_items(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PublicQuickAccessNavigation::validateAndNormalize([
            ['type' => 'dropdown', 'label' => 'Secciones', 'icon' => 'bars-3'],
        ]);
    }

    public function test_server_status_is_available_as_unique_exceptional_dropdown(): void
    {
        $catalog = PublicQuickAccessNavigation::catalog();
        $this->assertContains('server_status', array_column($catalog, 'type'));
        $items = PublicQuickAccessNavigation::validateAndNormalize([
            ['type' => 'server_status', 'label' => 'Estado de servidores', 'icon' => 'server-stack',
                'visible_to' => ['ACTIVO', 'RECLUTA']],
        ]);
        $this->assertSame('server_status', $items[0]['type']);
        $this->assertSame(['ACTIVO', 'RECLUTA'], $items[0]['visible_to']);
        $this->assertSame($items, PublicQuickAccessNavigation::normalizeStored($items));
    }

    public function test_duplicate_server_status_widget_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PublicQuickAccessNavigation::validateAndNormalize([
            ['type' => 'server_status', 'label' => 'Estado', 'icon' => 'server-stack'],
            ['type' => 'server_status', 'label' => 'Estado de nuevo', 'icon' => 'server-stack'],
        ]);
    }
}
