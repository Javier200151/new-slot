<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('homepage_settings')) {
            return;
        }

        DB::table('homepage_settings')
            ->where(function ($query): void {
                $query->whereNull('instagram_url')
                    ->orWhereIn('instagram_url', [
                        'https://www.instagram.com/squadalpha.es/',
                        'https://instagram.com/squadalpha.es/',
                    ]);
            })
            ->update([
                'instagram_url' => 'https://www.instagram.com/squadalpha_es/',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // No revertimos una URL pública correcta a la variante antigua.
    }
};
