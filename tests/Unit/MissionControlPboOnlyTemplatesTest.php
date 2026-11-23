<?php

namespace Tests\Unit;

use App\Services\MissionControl\MissionControlTelegramTemplates as Templates;
use PHPUnit\Framework\TestCase;

class MissionControlPboOnlyTemplatesTest extends TestCase
{
    public function test_all_file_check_previews_omit_html_and_include_addons(): void
    {
        foreach (['files_ready', 'files_missing', 'files_unknown'] as $kind) {
            $preview = Templates::preview($kind);
            $this->assertStringNotContainsString('HTML', strtoupper($preview));
            $this->assertStringContainsString('PBO', $preview);
            $this->assertStringContainsString('Arranque automático', $preview);
        }
        $this->assertStringContainsString('addons', mb_strtolower(Templates::preview('files_ready')));
        $this->assertStringContainsString('Addons', Templates::preview('files_missing'));
    }
}
