<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('public_quick_access_settings')) {
            return;
        }

        DB::transaction(function (): void {
            $settings = DB::table('public_quick_access_settings')->lockForUpdate()->first();
            if (! $settings) {
                return;
            }

            $items = json_decode((string) $settings->items, true);
            if (! is_array($items) || count($items) >= \App\Support\PublicQuickAccessNavigation::MAX_ITEMS) {
                // La configuración no se sobrescribe si la barra ya está llena.
                return;
            }

            foreach ($items as $item) {
                if (($item['type'] ?? null) === 'news_summary') {
                    return;
                }
            }

            $newItem = [
                'type' => 'news_summary',
                'label' => 'Novedades en SquadALPHA',
                'icon' => 'bell-alert',
            ];

            // Queda antes de "Volver arriba" sin alterar el orden existente.
            $scrollIndex = null;
            foreach ($items as $i => $item) {
                if (($item['type'] ?? null) === 'scroll_top') {
                    $scrollIndex = $i;
                    break;
                }
            }
            array_splice($items, $scrollIndex ?? count($items), 0, [$newItem]);

            DB::table('public_quick_access_settings')->where('id', $settings->id)->update([
                'items' => json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        // No retiramos opciones del menú: podrían haber sido reordenadas o
        // personalizadas por el administrador desde Filament.
    }
};
