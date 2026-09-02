<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operations', function (Blueprint $table): void {
            $table->foreignId('editor_ally_id')
                ->nullable()
                ->after('editor_id')
                ->constrained('allies')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('operations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('editor_ally_id');
        });
    }
};
