<?php

namespace Tests\Unit;

use App\Support\PublicNavigation;
use InvalidArgumentException;
use Tests\TestCase;

class PublicNavigationTest extends TestCase
{
    public function test_default_navigation_preserves_the_public_structure_without_a_fixed_area_dropdown(): void
    {
        $items = PublicNavigation::defaultItems();

        $this->assertCount(4, $items);
        $this->assertSame('Normativa', $items[0]['label']);
        $this->assertSame('Eventos', $items[1]['label']);
        $this->assertSame('Directos', $items[2]['label']);
        $this->assertSame('Comunidad', $items[3]['label']);
        $this->assertSame(
            ['activities', 'metopas', 'campaigns', 'organization', 'wiki'],
            array_column($items[3]['children'], 'destination'),
        );
    }

    public function test_invalid_destinations_are_not_rendered(): void
    {
        $normalized = PublicNavigation::normalizeItems([
            [
                'type' => 'link',
                'label' => 'No válido',
                'destination' => 'route-that-does-not-exist',
            ],
            [
                'type' => 'dropdown',
                'label' => 'Comunidad',
                'children' => [
                    ['destination' => 'activities', 'label' => 'Actividades'],
                    ['destination' => 'invalid', 'label' => 'Inválido'],
                ],
            ],
        ]);

        $this->assertCount(1, $normalized);
        $this->assertSame('dropdown', $normalized[0]['type']);
        $this->assertCount(1, $normalized[0]['children']);
        $this->assertSame('activities', $normalized[0]['children'][0]['destination']);
    }

    public function test_editor_catalog_always_contains_every_fixed_page(): void
    {
        $state = PublicNavigation::editorState(PublicNavigation::defaultItems());

        $catalog = array_column($state['available'], 'destination');
        $expected = array_keys(PublicNavigation::destinations());

        sort($catalog);
        sort($expected);

        $this->assertSame($expected, $catalog);
        $this->assertContains('forum', $catalog);
        $this->assertContains('roulette', $catalog);
    }

    public function test_duplicate_page_destinations_are_allowed_in_different_placements(): void
    {
        $all = array_keys(PublicNavigation::audienceOptions());

        $normalized = PublicNavigation::normalizeEditorState([
            [
                'type' => 'dropdown',
                'label' => 'RECLUTAMIENTO',
                'visible_to' => ['RECLUTA'],
                'children' => [
                    [
                        'destination' => 'forum',
                        'label' => 'Foro',
                        'visible_to' => ['RECLUTA'],
                    ],
                ],
            ],
            [
                'type' => 'dropdown',
                'label' => 'AREA 51',
                'visible_to' => ['ACTIVO'],
                'children' => [
                    [
                        'destination' => 'forum',
                        'label' => 'Foro',
                        'visible_to' => ['ACTIVO'],
                    ],
                    [
                        'destination' => 'roulette',
                        'label' => 'Ruleta',
                        'visible_to' => ['ACTIVO'],
                    ],
                ],
            ],
            [
                'type' => 'link',
                'destination' => 'forum',
                'label' => 'Foro público interno',
                'visible_to' => $all,
            ],
        ]);

        $this->assertSame('forum', $normalized[0]['children'][0]['destination']);
        $this->assertSame('forum', $normalized[1]['children'][0]['destination']);
        $this->assertSame('forum', $normalized[2]['destination']);
    }

    public function test_more_than_six_configured_columns_are_allowed_when_no_audience_sees_more_than_six(): void
    {
        $items = [];

        for ($i = 0; $i < 6; $i++) {
            $items[] = [
                'type' => 'link',
                'destination' => 'events',
                'label' => 'Activo ' . ($i + 1),
                'visible_to' => ['ACTIVO'],
            ];
        }

        $items[] = [
            'type' => 'link',
            'destination' => 'forum',
            'label' => 'Reclutamiento',
            'visible_to' => ['RECLUTA'],
        ];

        $normalized = PublicNavigation::normalizeEditorState($items);

        $this->assertCount(7, $normalized);
        $this->assertSame(
            6,
            PublicNavigation::visibleTopLevelCountForAudience($normalized, 'ACTIVO'),
        );
        $this->assertSame(
            1,
            PublicNavigation::visibleTopLevelCountForAudience($normalized, 'RECLUTA'),
        );
    }

    public function test_editor_rejects_seven_effective_columns_for_the_same_status(): void
    {
        $items = [];

        for ($i = 0; $i < 7; $i++) {
            $items[] = [
                'type' => 'link',
                'destination' => 'events',
                'label' => 'Enlace ' . ($i + 1),
                'visible_to' => ['ACTIVO'],
            ];
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ACTIVO: 7');

        PublicNavigation::normalizeEditorState($items);
    }

    public function test_dropdown_only_counts_for_an_audience_when_it_has_a_visible_child(): void
    {
        $items = [
            [
                'type' => 'dropdown',
                'label' => 'RECLUTAMIENTO',
                'visible_to' => ['ACTIVO', 'RECLUTA'],
                'children' => [
                    [
                        'destination' => 'forum',
                        'label' => 'Foro',
                        'visible_to' => ['RECLUTA'],
                    ],
                ],
            ],
        ];

        $this->assertSame(
            0,
            PublicNavigation::visibleTopLevelCountForAudience($items, 'ACTIVO'),
        );
        $this->assertSame(
            1,
            PublicNavigation::visibleTopLevelCountForAudience($items, 'RECLUTA'),
        );
    }

    public function test_legacy_items_without_visibility_are_visible_to_every_audience(): void
    {
        $item = [
            'type' => 'link',
            'destination' => 'events',
            'label' => 'Eventos',
        ];

        $this->assertTrue(PublicNavigation::itemVisibleForAudience($item, 'ACTIVO'));
        $this->assertTrue(PublicNavigation::itemVisibleForAudience($item, 'RECLUTA'));
        $this->assertTrue(PublicNavigation::itemVisibleForAudience($item, PublicNavigation::AUDIENCE_GUEST));
    }

    public function test_placed_item_must_have_at_least_one_audience(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('debe ser visible al menos');

        PublicNavigation::normalizeEditorState([
            [
                'type' => 'link',
                'destination' => 'events',
                'label' => 'Eventos',
                'visible_to' => [],
            ],
        ]);
    }
    public function test_custom_external_links_can_be_saved_at_top_level_and_inside_dropdowns(): void
    {
        $audiences = array_keys(PublicNavigation::audienceOptions());

        $normalized = PublicNavigation::normalizeEditorState([
            [
                'type' => 'external',
                'label' => 'Documentación',
                'url' => 'https://docs.example.com/newslot',
                'visible_to' => $audiences,
            ],
            [
                'type' => 'dropdown',
                'label' => 'Comunidad',
                'visible_to' => $audiences,
                'children' => [
                    [
                        'type' => 'external',
                        'label' => 'Wiki externa',
                        'url' => 'https://example.com/wiki',
                        'visible_to' => $audiences,
                    ],
                ],
            ],
        ]);

        $this->assertSame('external', $normalized[0]['type']);
        $this->assertSame('https://docs.example.com/newslot', $normalized[0]['url']);
        $this->assertSame('external', $normalized[1]['children'][0]['type']);
        $this->assertSame('https://example.com/wiki', $normalized[1]['children'][0]['url']);
        $this->assertTrue(PublicNavigation::itemIsExternal($normalized[0]));
        $this->assertSame('https://docs.example.com/newslot', PublicNavigation::itemUrl($normalized[0]));
    }

    public function test_custom_external_links_reject_non_http_urls(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('http:// o https://');

        PublicNavigation::normalizeEditorState([
            [
                'type' => 'external',
                'label' => 'No válido',
                'url' => 'javascript:alert(1)',
                'visible_to' => ['ACTIVO'],
            ],
        ]);
    }

}
