<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sqa_groups', function (Blueprint $table): void {
            $table->foreignId('coordinator_role_id')->nullable()->constrained('roles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sqa_groups', fn (Blueprint $table) => $table->dropConstrainedForeignId('coordinator_role_id'));
    }
};
