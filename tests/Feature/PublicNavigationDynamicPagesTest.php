<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Support\PublicNavigation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicNavigationDynamicPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertIsolatedTestDatabase();

        Schema::dropIfExists('pages');

        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content');
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('pages');

        parent::tearDown();
    }

    private function assertIsolatedTestDatabase(): void
    {
        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");
        $database = (string) config("database.connections.{$connection}.database");

        if ($driver !== 'sqlite' || $database !== ':memory:') {
            throw new \RuntimeException(
                'SEGURIDAD DE TESTS: este test elimina tablas. Debe ejecutarse exclusivamente con SQLite :memory:. '
                . "Conexión actual: {$connection} / {$driver} / {$database}."
            );
        }
    }

    public function test_filament_pages_are_available_in_the_navigation_catalogue(): void
    {
        Page::query()->create([
            'title' => 'Historia de Squad Alpha',
            'slug' => 'historia-sqa',
            'content' => '<p>Historia</p>',
            'is_published' => true,
        ]);

        Page::query()->create([
            'title' => 'Página en preparación',
            'slug' => 'pagina-en-preparacion',
            'content' => '<p>Borrador</p>',
            'is_published' => false,
        ]);

        $state = PublicNavigation::editorState(PublicNavigation::defaultItems());
        $catalogue = collect($state['available'])->keyBy('destination');

        $this->assertTrue($catalogue->has('page:historia-sqa'));
        $this->assertSame('Historia de Squad Alpha', $catalogue['page:historia-sqa']['label']);
        $this->assertSame('dynamic', $catalogue['page:historia-sqa']['source']);
        $this->assertTrue($catalogue['page:historia-sqa']['published']);

        $this->assertTrue($catalogue->has('page:pagina-en-preparacion'));
        $this->assertFalse($catalogue['page:pagina-en-preparacion']['published']);
    }

    public function test_dynamic_page_destination_uses_page_slug_and_only_renders_when_published(): void
    {
        Page::query()->create([
            'title' => 'Historia',
            'slug' => 'historia',
            'content' => '<p>Historia</p>',
            'is_published' => true,
        ]);

        Page::query()->create([
            'title' => 'Privada',
            'slug' => 'privada',
            'content' => '<p>Privada</p>',
            'is_published' => false,
        ]);

        $published = [
            'type' => 'link',
            'destination' => 'page:historia',
            'label' => 'Historia',
        ];

        $unpublished = [
            'type' => 'link',
            'destination' => 'page:privada',
            'label' => 'Privada',
        ];

        $this->assertSame(route('pages.show', 'historia'), PublicNavigation::itemUrl($published));
        $this->assertTrue(PublicNavigation::canDisplayNavigationItem($published));
        $this->assertFalse(PublicNavigation::canDisplayNavigationItem($unpublished));
    }

    public function test_normativa_and_faqs_are_not_duplicated_as_dynamic_catalogue_pages(): void
    {
        Page::query()->create([
            'title' => 'Normativa',
            'slug' => 'normativa',
            'content' => '<p>Normativa</p>',
            'is_published' => true,
        ]);

        Page::query()->create([
            'title' => 'FAQs',
            'slug' => 'faqs',
            'content' => '<p>FAQs</p>',
            'is_published' => true,
        ]);

        $catalogue = collect(PublicNavigation::editorState([])['available'])
            ->pluck('destination');

        $this->assertSame(1, $catalogue->filter(fn (string $destination): bool => $destination === 'normativa')->count());
        $this->assertSame(1, $catalogue->filter(fn (string $destination): bool => $destination === 'faqs')->count());
        $this->assertNotContains('page:normativa', $catalogue);
        $this->assertNotContains('page:faqs', $catalogue);
    }
}
