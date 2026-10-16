<?php

namespace Tests\Feature;

use Tests\TestCase;

class AlliedSignatureRenderingTest extends TestCase
{
    public function test_allied_signature_contains_only_the_clan_logo(): void
    {
        $user = (object) [
            'nick' => 'BEAR',
            'status' => (object) ['name' => 'ALIADO'],
            'ally' => (object) ['name' => 'BEAR', 'image' => 'allies/bear.png'],
        ];

        $html = view('firmas.show', compact('user'))->render();

        $this->assertStringContainsString('storage/allies/bear.png', $html);
        $this->assertStringContainsString('Logo BEAR', $html);
        $this->assertStringNotContainsString('class="banner"', $html);
        $this->assertStringNotContainsString('class="metopas"', $html);
        $this->assertSame(1, substr_count($html, '<html'));
    }
}
