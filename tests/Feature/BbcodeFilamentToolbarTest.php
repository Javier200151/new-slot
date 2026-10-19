<?php

namespace Tests\Feature;

use App\Filament\Forms\BbcodeTextarea;
use App\Filament\Forms\BbcodeToolbar;
use Tests\TestCase;

class BbcodeFilamentToolbarTest extends TestCase
{
    public function test_server_rendered_toolbar_contains_all_headings_and_alignments(): void
    {
        $html = (string) BbcodeToolbar::make();

        foreach (range(1, 6) as $level) {
            $this->assertStringContainsString('Encabezado ' . $level, $html);
        }
        foreach (['left', 'center', 'right', 'justify'] as $alignment) {
            $this->assertStringContainsString($alignment, $html);
        }
        $this->assertStringContainsString('data-sqa-bbcode-toolbar', $html);
        $this->assertStringContainsString('x-on:click.prevent.stop', $html);
    }

    public function test_bbcode_form_helper_preserves_the_default_textarea_contract(): void
    {
        $field = BbcodeTextarea::make('body');
        $this->assertInstanceOf(\Filament\Forms\Components\Textarea::class, $field);
    }
}
