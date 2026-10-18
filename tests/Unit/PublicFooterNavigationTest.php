<?php

namespace Tests\Unit;

use App\Support\PublicFooterNavigation;
use InvalidArgumentException;
use Tests\TestCase;

class PublicFooterNavigationTest extends TestCase
{
    public function test_footer_has_users_and_a_single_server_widget_by_default(): void
    {
        $items = PublicFooterNavigation::defaultItems();

        $this->assertSame('users', $items[0]['destination']);
        $this->assertSame('server_status', $items[1]['type']);
    }

    public function test_footer_accepts_repeated_page_buttons_independently(): void
    {
        $items = PublicFooterNavigation::validateAndNormalize([
            ['type' => 'link', 'destination' => 'users', 'label' => 'Usuarios'],
            ['type' => 'link', 'destination' => 'users', 'label' => 'Comunidad'],
        ]);

        $this->assertCount(2, $items);
        $this->assertSame('users', $items[1]['destination']);
    }

    public function test_dropdown_of_pages_is_not_accepted_in_footer(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PublicFooterNavigation::validateAndNormalize([
            ['type' => 'dropdown', 'label' => 'Comunidad', 'children' => []],
        ]);
    }

    public function test_server_widget_cannot_be_duplicated(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PublicFooterNavigation::validateAndNormalize([
            ['type' => 'server_status', 'label' => 'Estado'],
            ['type' => 'server_status', 'label' => 'Estado 2'],
        ]);
    }

    public function test_external_links_reject_javascript_scheme(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PublicFooterNavigation::validateAndNormalize([
            ['type' => 'external', 'label' => 'Inseguro', 'url' => 'javascript:alert(1)'],
        ]);
    }

    public function test_items_with_unknown_audiences_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PublicFooterNavigation::validateAndNormalize([
            ['type' => 'link', 'destination' => 'users', 'label' => 'Usuarios',
                'visible_to' => ['ESTADO_INVENTADO']],
        ]);
    }
}
