<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("UPDATE operations SET orbat = NULL WHERE orbat IS NOT NULL AND JSON_VALID(orbat) = 0");
        DB::statement("ALTER TABLE operations MODIFY orbat JSON NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE operations MODIFY orbat LONGTEXT NULL");
    }
};
