<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operations', function (Blueprint $table): void {
            $table->foreignId('metopa_id')
                ->nullable()
                ->after('editor_ally_id')
                ->constrained('metopas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('operations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('metopa_id');
        });
    }
};
