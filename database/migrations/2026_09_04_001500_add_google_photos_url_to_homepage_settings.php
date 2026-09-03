<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CURRENT_ALBUM_URL = 'https://photos.google.com/share/AF1QipNdq-gzduALgaiw4sLbUdtIhVqnU4BzSBXFqKgTg-PA5rADUy5nzNY9Meg2VY67Kw?key=WG9wYVUzWWxWeEFNR1YwUHctYU8wbjF6OWFkTkJn';

    public function up(): void
    {
        if (! Schema::hasTable('homepage_settings')) {
            return;
        }

        if (! Schema::hasColumn('homepage_settings', 'google_photos_url')) {
            Schema::table('homepage_settings', function (Blueprint $table): void {
                $table->text('google_photos_url')->nullable()->after('instagram_url');
            });
        }

        DB::table('homepage_settings')
            ->whereNull('google_photos_url')
            ->update([
                'google_photos_url' => self::CURRENT_ALBUM_URL,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('homepage_settings') && Schema::hasColumn('homepage_settings', 'google_photos_url')) {
            Schema::table('homepage_settings', function (Blueprint $table): void {
                $table->dropColumn('google_photos_url');
            });
        }
    }
};
