<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class FilamentAssetDeliveryTest extends TestCase
{
    public function test_panel_references_existing_static_assets_without_inlining_them(): void
    {
        $root = dirname(__DIR__, 2);
        $provider = file_get_contents($root . '/app/Providers/Filament/AdminPanelProvider.php');

        $this->assertNotFalse($provider);
        $this->assertStringContainsString('hash_file(\'sha256\'', $provider);
        $this->assertStringContainsString('<link rel="stylesheet"', $provider);
        $this->assertStringContainsString('<script src="', $provider);
        $this->assertStringNotContainsString('file_get_contents($path)', $provider);

        foreach ([
            'public/css/filament-custom.css',
            'public/css/personal-dashboard.css',
            'public/js/filament-orbat-layout.js',
        ] as $relativePath) {
            $this->assertFileExists($root . '/' . $relativePath);
        }
    }

    public function test_asset_versions_change_with_file_content(): void
    {
        $first = tempnam(sys_get_temp_dir(), 'sqa_asset_');
        $this->assertNotFalse($first);

        try {
            file_put_contents($first, 'version-one');
            $v1 = substr(hash_file('sha256', $first), 0, 16);
            file_put_contents($first, 'version-two');
            $v2 = substr(hash_file('sha256', $first), 0, 16);
            $this->assertNotSame($v1, $v2);
        } finally {
            @unlink($first);
        }
    }
}
