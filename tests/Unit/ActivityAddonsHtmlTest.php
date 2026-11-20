<?php

namespace Tests\Unit;

use App\Support\ActivityAddonsHtml;
use PHPUnit\Framework\TestCase;

class ActivityAddonsHtmlTest extends TestCase
{
    public function test_it_preserves_the_arma3_launcher_html_format(): void
    {
        $html = ActivityAddonsHtml::fromNames(['@ace', '@RHSUSAF']);
        $this->assertSame(2, substr_count($html, 'data-type="ModContainer"'));
        $this->assertStringContainsString('<td data-type="DisplayName">@ace</td>', $html);
        $this->assertStringContainsString('<td data-type="DisplayName">@RHSUSAF</td>', $html);
        $this->assertStringStartsWith('<html><head></head><body><table>', $html);
    }

    public function test_it_escapes_addon_names_and_keeps_pbo_basename(): void
    {
        $this->assertStringContainsString('&lt;addon&gt;', ActivityAddonsHtml::fromNames(['<addon>']));
        $this->assertSame('CO@30_OPLOT_SPART_NW.tem_anizay.html',
            ActivityAddonsHtml::htmlFilename('CO@30_OPLOT_SPART_NW.tem_anizay.pbo'));
    }
}
