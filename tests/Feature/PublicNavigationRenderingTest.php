<?php

namespace Tests\Feature;

use App\Models\PublicNavigationSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicNavigationRenderingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('public_navigation_settings')) {
            Schema::create('public_navigation_settings', function (Blueprint $table): void {
                $table->id();
                $table->json('items');
                $table->timestamps();
            });
        }
    }

    public function test_public_header_uses_the_filament_navigation_configuration(): void
    {
        PublicNavigationSetting::query()->create([
            'items' => [
                [
                    'type' => 'link',
                    'label' => 'Agenda',
                    'destination' => 'events',
                ],
                [
                    'type' => 'dropdown',
                    'label' => 'Información',
                    'children' => [
                        ['label' => 'Normas', 'destination' => 'normativa'],
                        ['label' => 'Wiki', 'destination' => 'wiki'],
                    ],
                ],
            ],
        ]);

        $html = view('partials.public-header')->render();

        $this->assertStringContainsString('Agenda', $html);
        $this->assertStringContainsString('Información', $html);
        $this->assertStringContainsString('Normas', $html);
        $this->assertStringContainsString('Wiki', $html);
    }

    public function test_legacy_forum_link_is_only_rendered_in_the_footer(): void
    {
        $headerHtml = view('partials.public-header')->render();
        $footerHtml = view('partials.public-footer', [
            'footerLinkUrl' => route('home'),
            'footerLinkLabel' => 'Inicio',
        ])->render();

        $this->assertStringNotContainsString('Foro antiguo', $headerHtml);
        $this->assertStringContainsString('Foro antiguo', $footerHtml);
        $this->assertSame(1, substr_count($headerHtml . $footerHtml, 'Foro antiguo'));
    }
}
