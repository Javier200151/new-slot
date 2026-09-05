<?php

namespace Tests\Unit;

use App\Support\BriefingMarkup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BriefingMarkupTest extends TestCase
{
    #[Test]
    public function it_converts_legacy_rich_editor_html_to_bbcode(): void
    {
        $value = '<p><strong>Situación</strong><br><a href="https://example.com">Mapa</a></p>';

        $converted = BriefingMarkup::toEditor($value);

        $this->assertStringContainsString('[b]Situación[/b]', $converted);
        $this->assertStringContainsString('[url=https://example.com]Mapa[/url]', $converted);
        $this->assertStringNotContainsString('<strong>', $converted);
    }

    #[Test]
    public function it_renders_safe_bbcode_without_allowing_script_image_urls(): void
    {
        $html = BriefingMarkup::render(
            '[color=#ff8800]Naranja[/color] [img]javascript:alert(1)[/img] <script>alert(1)</script>'
        )->toHtml();

        $this->assertStringContainsString('color:#ff8800', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('src="javascript:', $html);
    }
}
