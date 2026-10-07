<?php

namespace Tests\Feature;

use App\Services\HomepageGooglePhotosService;
use App\Services\HomepageInstagramService;
use App\Services\HomepageVodService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('homepage_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('recruitment_open')->default(false);
            $table->string('contact_email')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('x_url')->nullable();
            $table->string('youtube_url')->nullable();
            $table->text('discord_invite_url')->nullable();
            $table->text('google_photos_url')->nullable();
            $table->string('news_title')->default('Actualidad de Squad ALPHA');
            $table->text('news_intro')->nullable();
            $table->string('streams_title')->default('Últimos VODs de la comunidad');
            $table->text('streams_intro')->nullable();
            $table->timestamps();
        });

        DB::table('homepage_settings')->insert([
            'recruitment_open' => false,
            'instagram_url' => 'https://www.instagram.com/squadalpha_es/',
            'x_url' => 'https://x.com/SquadALPHA_ES',
            'youtube_url' => 'https://www.youtube.com/c/SquadALPHA',
            'discord_invite_url' => 'https://discord.gg/squadalpha-test',
            'news_title' => 'Actualidad de Squad ALPHA',
            'streams_title' => 'Últimos VODs de la comunidad',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('homepage_news', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 180);
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('image')->nullable();
            $table->string('external_url')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->timestamps();
        });

        $this->mock(HomepageVodService::class)
            ->shouldReceive('latest')
            ->once()
            ->with(6)
            ->andReturn(collect());

        $this->mock(HomepageInstagramService::class)
            ->shouldReceive('latest')
            ->once()
            ->with(3)
            ->andReturn(collect());

        $this->mock(HomepageGooglePhotosService::class)
            ->shouldReceive('latest')
            ->once()
            ->andReturnUsing(fn (): Collection => collect());
    }

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
