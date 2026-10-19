<?php

namespace Tests\Unit;

use App\Support\BbcodeMarkup;
use PHPUnit\Framework\TestCase;

class BbcodeMarkupTest extends TestCase
{
    public function test_it_renders_common_formatting_and_lists(): void
    {
        $html = BbcodeMarkup::render(
            "[b]Negrita[/b]\n[list][*]Uno[*]Dos[/list]\n[list=A][*]Alpha[*]Beta[/list]"
        )->toHtml();

        $this->assertStringContainsString('<strong>Negrita</strong>', $html);
        $this->assertStringContainsString('<ul class="forum-rich__list bbcode-rich__list">', $html);
        $this->assertStringContainsString('<ol class="forum-rich__list bbcode-rich__list" type="A">', $html);
        $this->assertStringContainsString('<li>Uno</li>', $html);
        $this->assertStringContainsString('<li>Beta</li>', $html);
    }

    public function test_it_supports_nested_lists_and_unordered_variants(): void
    {
        $html = BbcodeMarkup::render(
            '[list=square][*]Principal[list=1][*]Uno[*]Dos[/list][*]Segundo[/list]'
        )->toHtml();

        $this->assertStringContainsString('list-style-type:square', $html);
        $this->assertStringContainsString('type="1"', $html);
        $this->assertStringContainsString('<li>Principal<ol', $html);
        $this->assertStringContainsString('<li>Segundo</li>', $html);
    }

    public function test_it_allows_http_gifs_but_rejects_unsafe_urls(): void
    {
        $html = BbcodeMarkup::render(
            '[img]https://example.com/demo.gif[/img] [url=javascript:alert(1)]No[/url] [img]javascript:alert(1)[/img]'
        )->toHtml();

        $this->assertStringContainsString('src="https://example.com/demo.gif"', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
        $this->assertSame(1, substr_count($html, '<img '));
    }

    public function test_it_preserves_legacy_rich_editor_colors(): void
    {
        $legacy = '<p><span class="color" data-color="red">Alerta roja</span></p>';

        $editor = BbcodeMarkup::toEditor($legacy);
        $html = BbcodeMarkup::render($legacy)->toHtml();

        $this->assertStringContainsString('[color=red]Alerta roja[/color]', $editor);
        $this->assertStringContainsString('class="color"', $html);
        $this->assertStringContainsString('data-color="red"', $html);
        $this->assertStringContainsString('--color:#f87171', $html);
    }

    public function test_it_converts_legacy_rich_html_to_bbcode_for_editing(): void
    {
        $text = BbcodeMarkup::toEditor(
            '<p><strong>Texto</strong></p><ul><li>Uno</li><li>Dos</li></ul>'
        );

        $this->assertStringContainsString('[b]Texto[/b]', $text);
        $this->assertStringContainsString('[list]', $text);
        $this->assertStringContainsString('[*]Uno', $text);
        $this->assertStringContainsString('[*]Dos', $text);
        $this->assertStringContainsString('[/list]', $text);
    }
    public function test_it_renders_all_six_headings_and_four_alignments_without_unsafe_attributes(): void
    {
        $source = implode("\n", [
            '[h1]Principal[/h1]', '[h2]Dos[/h2]', '[h3]Tres[/h3]',
            '[h4]Cuatro[/h4]', '[h5]Cinco[/h5]', '[h6]Seis[/h6]',
            '[left]Izquierda[/left]', '[center]Centro[/center]',
            '[right]Derecha[/right]', '[justify]Justificado[/justify]', '[align=justify]También justificado[/align]',
            '[h1]<script>alert(1)</script>[/h1]',
        ]);

        $html = BbcodeMarkup::render($source)->toHtml();

        foreach (range(1, 6) as $level) {
            $this->assertStringContainsString('<h' . $level . ' class="forum-rich__h' . $level, $html);
        }
        foreach (['left', 'center', 'right', 'justify'] as $alignment) {
            $this->assertStringContainsString('bbcode-rich__align--' . $alignment, $html);
        }
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_legacy_headings_retain_their_individual_levels(): void
    {
        $source = BbcodeMarkup::toEditor('<h1>Uno</h1><h4>Cuatro</h4><h6>Seis</h6>');
        $this->assertStringContainsString('[h1]Uno[/h1]', $source);
        $this->assertStringContainsString('[h4]Cuatro[/h4]', $source);
        $this->assertStringContainsString('[h6]Seis[/h6]', $source);
    }

}
