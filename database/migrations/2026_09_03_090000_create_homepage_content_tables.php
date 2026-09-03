<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('homepage_settings')) {
            Schema::create('homepage_settings', function (Blueprint $table): void {
                $table->id();
                $table->boolean('recruitment_open')->default(false);
                $table->string('contact_email')->nullable();
                $table->string('instagram_url')->nullable();
                $table->string('news_title')->default('Actualidad de Squad ALPHA');
                $table->text('news_intro')->nullable();
                $table->string('streams_title')->default('Últimos VODs de la comunidad');
                $table->text('streams_intro')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('homepage_news')) {
            Schema::create('homepage_news', function (Blueprint $table): void {
                $table->id();
                $table->string('title', 180);
                $table->text('excerpt')->nullable();
                $table->longText('body')->nullable();
                $table->string('image')->nullable();
                $table->string('external_url')->nullable();
                $table->boolean('is_published')->default(true)->index();
                $table->timestamp('published_at')->nullable()->index();
                $table->unsignedSmallInteger('sort_order')->default(100);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('contact_submissions')) {
            Schema::create('contact_submissions', function (Blueprint $table): void {
                $table->id();
                $table->string('email');
                $table->text('message');
                $table->boolean('is_recruitment')->default(false)->index();
                $table->boolean('accepted_rules')->default(false);
                $table->boolean('is_adult')->default(false);
                $table->boolean('accepts_contributions')->default(false);
                $table->boolean('has_required_game_content')->default(false);
                $table->boolean('tuesday_available')->nullable();
                $table->boolean('friday_available')->nullable();
                $table->boolean('has_previous_experience')->default(false);
                $table->boolean('accepted_privacy')->default(false);
                $table->boolean('accepted_contact')->default(false);
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->timestamp('read_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('homepage_settings') && DB::table('homepage_settings')->count() === 0) {
            DB::table('homepage_settings')->insert([
                'recruitment_open' => false,
                'contact_email' => 'planamayorsquadalpha@gmail.com',
                'instagram_url' => 'https://www.instagram.com/squadalpha_es/',
                'news_title' => 'Actualidad de Squad ALPHA',
                'news_intro' => 'Noticias, novedades y vida de la comunidad.',
                'streams_title' => 'Últimos VODs de la comunidad',
                'streams_intro' => 'Revive las últimas retransmisiones guardadas por nuestros streamers.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_submissions');
        Schema::dropIfExists('homepage_news');
        Schema::dropIfExists('homepage_settings');
    }
};
