<?php

namespace Tests\Feature;

use App\Support\BriefingMarkup;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BriefingImageRouteTest extends TestCase
{
    public function test_local_briefing_images_use_same_origin_media_route(): void
    {
        $this->assertSame(
            '/media/briefings/example.jpg',
            BriefingMarkup::imageUrl('activities/briefings/example.jpg'),
        );
    }

    public function test_briefing_media_route_serves_public_image_without_app_url_dependency(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put(
            'activities/briefings/example.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
        );

        $response = $this->get('/media/briefings/example.png');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_briefing_media_route_does_not_expose_non_images(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('activities/briefings/example.txt', 'not an image');

        $this->get('/media/briefings/example.txt')->assertNotFound();
    }
}
