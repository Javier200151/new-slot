<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Tests\TestCase;

class PublicPageTest extends TestCase
{
    public function test_normativa_view_renders_the_stored_page_content(): void
    {
        $page = new Page([
            'title' => 'Normativa',
            'slug' => 'normativa',
            'content' => '<h2>Respeto y convivencia</h2>',
            'is_published' => true,
        ]);

        $view = $this->view('pages.show', [
            'page' => $page,
            'content' => new HtmlString('<h2>Respeto y convivencia</h2>'),
        ]);

        $view
            ->assertSee('Normativa')
            ->assertSee('<h2>Respeto y convivencia</h2>', escape: false)
            ->assertSee(route('pages.show', 'normativa'), escape: false)
            ->assertSee('css/pages.css', escape: false)
            ->assertSee('Grupo de Simulación Táctica en Arma 3 y Arma Reforger')
            ->assertSee('Volver al inicio');
    }

    public function test_a_published_page_is_accessible_directly_by_its_slug(): void
    {
        Route::bind('page', fn (string $slug): Page => new Page([
            'title' => 'Página de prueba',
            'slug' => $slug,
            'content' => '<p>Contenido de prueba</p>',
            'is_published' => true,
        ]));

        $this->get('/prueba')
            ->assertOk()
            ->assertSee('Página de prueba')
            ->assertSee('Contenido de prueba');
    }

    public function test_an_unpublished_page_is_not_publicly_accessible(): void
    {
        Route::bind('page', fn (string $slug): Page => new Page([
            'title' => 'Página privada',
            'slug' => $slug,
            'content' => '<p>Contenido privado</p>',
            'is_published' => false,
        ]));

        $this->get('/privada')->assertNotFound();
    }

    public function test_specific_application_routes_take_priority_over_dynamic_pages(): void
    {
        $routes = app('router')->getRoutes();

        $this->assertSame('filament.admin.pages.dashboard', $routes->match(Request::create('/admin'))->getName());
        $this->assertSame('login', $routes->match(Request::create('/login'))->getName());
        $this->assertSame('metopas.index', $routes->match(Request::create('/metopas'))->getName());
        $this->assertSame('events.index', $routes->match(Request::create('/eventos'))->getName());
        $this->assertSame('campaigns.show', $routes->match(Request::create('/campanas/1'))->getName());
        $this->assertSame('pages.show', $routes->match(Request::create('/prueba'))->getName());
    }
}
