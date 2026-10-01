<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('public_navigation_settings')) {
            return;
        }

        Schema::create('public_navigation_settings', function (Blueprint $table): void {
            $table->id();
            $table->json('items');
            $table->timestamps();
        });

        DB::table('public_navigation_settings')->insert([
            'items' => json_encode([
                [
                    'type' => 'link',
                    'label' => 'Normativa',
                    'destination' => 'normativa',
                ],
                [
                    'type' => 'link',
                    'label' => 'Eventos',
                    'destination' => 'events',
                ],
                [
                    'type' => 'link',
                    'label' => 'Directos',
                    'destination' => 'streams',
                ],
                [
                    'type' => 'dropdown',
                    'label' => 'Comunidad',
                    'children' => [
                        ['label' => 'Actividades', 'destination' => 'activities'],
                        ['label' => 'Metopas', 'destination' => 'metopas'],
                        ['label' => 'Campañas', 'destination' => 'campaigns'],
                        ['label' => 'Organigrama', 'destination' => 'organization'],
                        ['label' => 'Wiki', 'destination' => 'wiki'],
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('public_navigation_settings');
    }
};
