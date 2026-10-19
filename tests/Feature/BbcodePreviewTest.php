<?php

namespace Tests\Feature;

use Tests\TestCase;

class BbcodePreviewTest extends TestCase
{
    public function test_guest_cannot_generate_a_forum_preview(): void
    {
        $this->postJson(route('bbcode.preview'), ['body' => '[h1]Borrador[/h1]'])
            ->assertUnauthorized();
    }

    public function test_preview_renders_safe_bbcode_without_publishing_anything(): void
    {
        // Avoid database dependencies: the endpoint itself does not read or write models.
        $this->withoutMiddleware();

        $this->postJson(route('bbcode.preview'), [
            'body' => '[h1]Título[/h1][justify]Contenido[/justify][img]javascript:alert(1)[/img]',
        ])->assertOk()
            ->assertJsonPath('html', \App\Support\BbcodeMarkup::render('[h1]Título[/h1][justify]Contenido[/justify][img]javascript:alert(1)[/img]')->toHtml())
            ->assertJsonStructure(['html']);
    }

    public function test_preview_rejects_oversized_payload(): void
    {
        $this->withoutMiddleware();
        $this->postJson(route('bbcode.preview'), ['body' => str_repeat('x', 50001)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');
    }
}
