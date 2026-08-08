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
            ->assertSee('Crear cuenta')
            ->assertSee('Grupo de Simulación Táctica en Arma 3 y Arma Reforger')
            ->assertSee('Squad ALPHA en X')
            ->assertSee('Squad ALPHA en Instagram')
            ->assertSee('Squad ALPHA en YouTube')
            ->assertSee('Servidor de Discord de Squad ALPHA')
            ->assertSee('Volver arriba ↑');
    }
}
