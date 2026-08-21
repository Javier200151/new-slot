<?php

namespace Tests\Feature;

use Filament\Forms\Components\RichEditor;
use ReflectionClass;
use Tests\TestCase;

class RichEditorConfigurationTest extends TestCase
{
    public function test_rich_editors_keep_the_default_toolbar_and_include_text_color(): void
    {
        $editor = RichEditor::make('content');
        $reflection = new ReflectionClass($editor);

        $this->assertNull(
            $reflection->getProperty('toolbarButtons')->getValue($editor),
            'La barra predeterminada no debe ser reemplazada.',
        );
        $this->assertContains(
            ['type' => 'enable', 'buttons' => ['textColor']],
            $reflection->getProperty('toolbarButtonsModifications')->getValue($editor),
        );
    }
}
