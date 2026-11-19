<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platforms', function (Blueprint $table): void {
            $table->boolean('has_pbo')->default(false);
        });

        Schema::table('activities', function (Blueprint $table): void {
            $table->string('pbo_path', 512)->nullable();
            $table->unsignedBigInteger('pbo_size_bytes')->nullable();
            $table->foreignId('pbo_uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('pbo_uploaded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table): void {
            $table->dropForeign(['pbo_uploaded_by']);
            $table->dropColumn(['pbo_path', 'pbo_size_bytes', 'pbo_uploaded_by', 'pbo_uploaded_at']);
        });

        Schema::table('platforms', function (Blueprint $table): void {
            $table->dropColumn('has_pbo');
        });
    }
};
