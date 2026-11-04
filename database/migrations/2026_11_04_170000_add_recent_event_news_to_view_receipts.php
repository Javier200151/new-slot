<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_view_receipts', function (Blueprint $table): void {
            $table->json('event_news_structure')->nullable()->after('orbat_slot_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('event_view_receipts', function (Blueprint $table): void {
            $table->dropColumn('event_news_structure');
        });
    }
};
