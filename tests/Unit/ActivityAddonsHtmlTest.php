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
        $this->assertStringStartsWith('<?xml version="1.0" encoding="utf-8"?>', $html);
        $this->assertStringContainsString('<meta name="arma:Type" content="list" />', $html);
        $this->assertStringContainsString('<meta name="generator" content="Arma 3 Launcher - https://arma3.com" />', $html);
    }

    public function test_it_adds_steam_workshop_links_and_keeps_names_without_urls(): void
    {
        $html = ActivityAddonsHtml::fromAddons([
            ['name' => 'ALiVE', 'download_url' => 'https://steamcommunity.com/sharedfiles/filedetails/?id=620260972'],
            ['name' => '@Local', 'download_url' => null],
        ]);
        $this->assertSame(2, substr_count($html, 'data-type="ModContainer"'));
        $this->assertSame(1, substr_count($html, 'data-type="Link"'));
        $this->assertStringContainsString('<span class="from-steam">Steam</span>', $html);
        $this->assertStringContainsString('<a href="https://steamcommunity.com/sharedfiles/filedetails/?id=620260972" data-type="Link">', $html);
        $this->assertStringContainsString('<td data-type="DisplayName">@Local</td>', $html);
        $this->assertStringContainsString('Created by Arma 3 Launcher by Bohemia Interactive.', $html);
    }

    public function test_it_escapes_urls_and_rejects_unsafe_schemes(): void
    {
        $html = ActivityAddonsHtml::fromAddons([
            ['name' => '<script>alert(1)</script>', 'download_url' => 'javascript:alert(1)'],
            ['name' => 'Another', 'download_url' => 'https://example.com/mod?a=1&b=2'],
        ]);
        $this->assertStringNotContainsString('href="javascript:', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('mod?a=1&amp;b=2', $html);
    }

    public function test_it_handles_different_addon_counts(): void
    {
        foreach ([1, 5, 23, 50] as $count) {
            $html = ActivityAddonsHtml::fromNames(array_map(fn ($i) => '@Addon_' . $i, range(1, $count)));
            $this->assertSame($count, substr_count($html, 'data-type="ModContainer"'));
        }
    }

    public function test_it_escapes_addon_names_and_keeps_pbo_basename(): void
    {
        $this->assertStringContainsString('&lt;addon&gt;', ActivityAddonsHtml::fromNames(['<addon>']));
        $this->assertSame('CO@30_OPLOT_SPART_NW.tem_anizay.html',
            ActivityAddonsHtml::htmlFilename('CO@30_OPLOT_SPART_NW.tem_anizay.pbo'));
    }
}
