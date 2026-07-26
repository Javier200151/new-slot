<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sqa_groups', function (Blueprint $table) {
            $table->string('color')->nullable()->after('image');
            $table->unsignedInteger('display_order')->nullable()->after('color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sqa_groups', function (Blueprint $table) {
            $table->dropColumn([
                'color',
                'display_order',
            ]);
        });
    }
};
