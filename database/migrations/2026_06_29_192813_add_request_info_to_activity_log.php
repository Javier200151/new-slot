<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->string('ip_address')->nullable()->after('properties');
            $table->text('user_agent')->nullable()->after('ip_address');
            $table->string('url')->nullable()->after('user_agent');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropColumn([
                'ip_address',
                'user_agent',
                'url',
            ]);
        });
    }
};