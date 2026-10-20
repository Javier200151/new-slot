<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sqa_easter_eggs', function (Blueprint $table): void {
            $table->string('key', 40)->primary();
            $table->boolean('enabled')->default(false);
            $table->string('image_path')->nullable();
            $table->string('audio_path')->nullable();
            $table->timestamps();
        });

        Schema::create('sqa_easter_egg_404_phrases', function (Blueprint $table): void {
            $table->id();
            $table->text('phrase');
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        $now = now();
        foreach (['grenade', 'error404', 'nvg', 'zeus', 'konami', 'fallujah_2012'] as $key) {
            DB::table('sqa_easter_eggs')->insert([
                'key' => $key,
                'enabled' => $key !== 'konami' && $key !== 'zeus',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Solo frases autorizadas por la comunidad; siguen siendo editables y eliminables.
        foreach ([
            'ALPHA, ALPHA, aquí UNIFORM. Te has ido más allá del LA1, retroceda inmediatamente.',
            '¡Malla errónea!',
            'Vete a hacer perímetro y saca el arma, aquí no hay nada que ver.',
        ] as $position => $phrase) {
            DB::table('sqa_easter_egg_404_phrases')->insert([
                'phrase' => $phrase,
                'enabled' => true,
                'position' => $position,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sqa_easter_egg_404_phrases');
        Schema::dropIfExists('sqa_easter_eggs');
    }
};
