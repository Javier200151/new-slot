<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('ally_id')->nullable()->constrained('allies')->nullOnDelete();
        });
        Schema::table('event_slots', function (Blueprint $table): void {
            $table->string('ally_nickname', 80)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_slots', fn (Blueprint $table) => $table->dropColumn('ally_nickname'));
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('ally_id');
        });
    }
};
