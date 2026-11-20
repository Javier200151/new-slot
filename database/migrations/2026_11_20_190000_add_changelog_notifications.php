<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_rules', function (Blueprint $table): void {
            $table->json('recipient_status_ids')->nullable();
        });
        Schema::table('changelog_entries', function (Blueprint $table): void {
            $table->timestamp('notified_at')->nullable()->index();
        });

        // No enviar avisos de todas las publicaciones históricas al instalar.
        DB::table('changelog_entries')->where('is_published', true)
            ->where(function ($query): void {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            })->update(['notified_at' => now()]);

        $statuses = DB::table('status')->whereIn('name', ['ACTIVO', 'RESERVA'])
            ->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        DB::table('notification_rules')->insertOrIgnore([
            'key' => 'changelog_published',
            'enabled' => true,
            'max_age_value' => null,
            'max_age_unit' => 'months',
            'recipient_status_ids' => json_encode($statuses),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('notification_rules')->where('key', 'changelog_published')->delete();
        Schema::table('changelog_entries', function (Blueprint $table): void {
            $table->dropColumn('notified_at');
        });
        Schema::table('notification_rules', function (Blueprint $table): void {
            $table->dropColumn('recipient_status_ids');
        });
    }
};
