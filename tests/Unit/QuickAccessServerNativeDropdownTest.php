<?php

namespace Tests\Unit;

use Tests\TestCase;

class QuickAccessServerNativeDropdownTest extends TestCase
{
    public function test_footer_and_sidebar_render_the_same_server_dropdown_content(): void
    {
        $footer = view('partials.public-server-status-dropdown', [
            'mode' => 'footer',
            'label' => 'Estado de servidores',
        ])->render();

        $sidebar = view('partials.public-server-status-dropdown', [
            'mode' => 'quick',
            'label' => 'Estado de servidores',
            'icon' => 'server-stack',
        ])->render();

        foreach ([$footer, $sidebar] as $html) {
            $this->assertStringContainsString('data-infrastructure-status', $html);
            $this->assertStringContainsString('data-endpoint=', $html);
            $this->assertStringContainsString('data-credential-base=', $html);
            $this->assertStringContainsString('footer-infrastructure__services', $html);
            $this->assertStringContainsString('data-ts3-block', $html);
            $this->assertStringContainsString('data-ts3-users', $html);
            $this->assertStringContainsString('data-ts3-close', $html);
        }

        $this->assertStringContainsString('footer-infrastructure__trigger', $footer);
        $this->assertStringContainsString('<summary', $sidebar);
        $this->assertStringContainsString('sqa-quick-access__server-trigger', $sidebar);
        $this->assertStringContainsString('sqa-quick-access__server-panel', $sidebar);
        $this->assertStringNotContainsString('data-quick-access-server-trigger', $sidebar);
    }

    public function test_both_locations_use_the_same_blade_component(): void
    {
        $footer = file_get_contents(resource_path('views/partials/public-footer.blade.php'));
        $quick = file_get_contents(resource_path('views/partials/public-quick-access.blade.php'));

        $this->assertStringContainsString("@include('partials.public-server-status-dropdown'", $footer);
        $this->assertStringContainsString("@include('partials.public-server-status-dropdown'", $quick);
    }
}
