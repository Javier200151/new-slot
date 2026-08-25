<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_media', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Evento
            |--------------------------------------------------------------------------
            */

            $table
                ->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();


            /*
            |--------------------------------------------------------------------------
            | Usuario que lo añadió
            |--------------------------------------------------------------------------
            |
            | Guardamos el usuario, no solamente el streamer.
            |
            | Así siempre podemos mostrar:
            |
            | "Añadido por Abnaxus"
            |
            */

            $table
                ->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();


            /*
            |--------------------------------------------------------------------------
            | Tipo
            |--------------------------------------------------------------------------
            |
            | clip
            | vod
            |
            */

            $table
                ->string('type', 20)
                ->index();


            /*
            |--------------------------------------------------------------------------
            | Plataforma
            |--------------------------------------------------------------------------
            |
            | youtube
            | twitch
            |
            */

            $table
                ->string('provider', 20)
                ->index();


            /*
            |--------------------------------------------------------------------------
            | URL original
            |--------------------------------------------------------------------------
            */

            $table->string(
                'url',
                500
            );


            /*
            |--------------------------------------------------------------------------
            | Identificador externo
            |--------------------------------------------------------------------------
            |
            | Ejemplos:
            |
            | YouTube:
            | dQw4w9WgXcQ
            |
            | Twitch clip:
            | AwkwardHelplessSalamander...
            |
            | Nos servirá después para generar el embed.
            |
            */

            $table
                ->string(
                    'external_id',
                    191
                )
                ->nullable();


            /*
            |--------------------------------------------------------------------------
            | Título
            |--------------------------------------------------------------------------
            */

            $table
                ->string('title')
                ->nullable();


            /*
            |--------------------------------------------------------------------------
            | Fechas
            |--------------------------------------------------------------------------
            */

            $table->timestamps();


            /*
            |--------------------------------------------------------------------------
            | Índices
            |--------------------------------------------------------------------------
            */

            $table->index([
                'event_id',
                'type',
            ]);

            /*
             * Evitamos añadir exactamente
             * el mismo enlace dos veces al
             * mismo evento.
             */

            $table->unique([
                'event_id',
                'url',
            ]);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'event_media'
        );
    }
};