<?php

namespace Tests\Unit;

use App\Support\BriefingSectionImages;
use PHPUnit\Framework\TestCase;

class BriefingSectionImagesTest extends TestCase
{
    public function test_legacy_single_image_is_exposed_as_gallery_item(): void
    {
        $images = BriefingSectionImages::stored([
            'image' => 'https://example.com/map.jpg',
            'image_position' => 'right',
            'image_alignment' => 'center',
            'image_width' => '50',
            'image_caption' => 'Mapa de situación',
        ]);

        $this->assertCount(1, $images);
        $this->assertSame('https://example.com/map.jpg', $images[0]['image']);
        $this->assertSame('right', $images[0]['image_position']);
        $this->assertSame('50', $images[0]['image_width']);
        $this->assertSame('Mapa de situación', $images[0]['image_caption']);
    }

    public function test_multiple_images_keep_their_configured_order(): void
    {
        $images = BriefingSectionImages::stored([
            'images' => [
                [
                    'image' => 'https://example.com/one.jpg',
                    'image_position' => 'top',
                    'image_width' => '66',
                ],
                [
                    'image' => 'https://example.com/two.jpg',
                    'image_position' => 'bottom',
                    'image_width' => '33',
                ],
            ],
        ]);

        $this->assertSame(
            ['https://example.com/one.jpg', 'https://example.com/two.jpg'],
            array_column($images, 'image')
        );
        $this->assertSame(['top', 'bottom'], array_column($images, 'image_position'));
    }

    public function test_full_width_side_image_is_normalized_to_top(): void
    {
        $images = BriefingSectionImages::stored([
            'images' => [[
                'image' => 'https://example.com/full.jpg',
                'image_position' => 'left',
                'image_width' => '100',
            ]],
        ]);

        $this->assertSame('top', $images[0]['image_position']);
    }
}
