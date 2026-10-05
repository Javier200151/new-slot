<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('personal_dashboard_widgets')) {
            return;
        }

        $defaults = [
            'recruitment-approved' => '2x2',
            'recruitment-promotions' => '2x2',
            'recruitment-dismissals' => '2x2',
            'upcoming-veterancies' => '2x2',
            'pending-veterancies' => '2x2',
            'mini-calendar' => '4x3',
            'reminders' => '2x2',
            'quick-links' => '2x1',
            'quick-search' => '2x2',
        ];

        foreach ($defaults as $type => $size) {
            DB::table('personal_dashboard_widgets')
                ->where('type', $type)
                ->whereIn('size', ['square', 'wide', '', null])
                ->update(['size' => $size]);
        }
    }

    public function down(): void
    {
        // Los tamaños nuevos contienen más información que square/wide.
        // No se degradan automáticamente para evitar perder preferencias.
    }
};
