<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_homepage_renders_the_responsive_public_navigation(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('data-nav-toggle', escape: false)
            ->assertSee('aria-controls="public-navigation"', escape: false)
            ->assertSee('data-nav-menu', escape: false)
            ->assertSee('Iniciar sesión')
            ->assertSee('Crear cuenta');
    }
}
