<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('activity_types', function (Blueprint $table): void {
            $table->json('participation_status_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('activity_types', function (Blueprint $table): void {
            $table->dropColumn('participation_status_ids');
        });
    }
};
