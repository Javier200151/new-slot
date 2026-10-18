<?php

namespace Tests\Feature;

use App\Models\PublicFooterSetting;
use App\Support\PublicFooterNavigation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicFooterNavigationRenderingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('public_footer_settings')) {
            Schema::create('public_footer_settings', function (Blueprint $table): void {
                $table->id();
                $table->json('items');
                $table->timestamps();
            });
        }

        PublicFooterSetting::query()->delete();
    }

    public function test_footer_renders_only_saved_buttons_and_keeps_legacy_link(): void
    {
        PublicFooterSetting::query()->create(['items' => [
            ['type' => 'link', 'destination' => 'users', 'label' => 'Miembros'],
            ['type' => 'link', 'destination' => 'faqs', 'label' => 'Consultas'],
        ]]);

        $html = view('partials.public-footer', [
            'footerLinkUrl' => route('home'),
            'footerLinkLabel' => 'Inicio',
        ])->render();

        $this->assertStringContainsString('Miembros', $html);
        $this->assertStringContainsString('Consultas', $html);
        $this->assertStringNotContainsString('data-infrastructure-status', $html);
        $this->assertStringContainsString('Foro antiguo', $html);
    }

    public function test_server_widget_never_exposes_itself_to_guest(): void
    {
        PublicFooterSetting::query()->create(['items' => [
            ['type' => 'server_status', 'label' => 'Servidores',
                'visible_to' => ['__guest__', 'ACTIVO']],
        ]]);

        $this->assertSame([], PublicFooterNavigation::visibleItems());
    }
}
