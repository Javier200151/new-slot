<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_aars', function (Blueprint $table): void {
            $table->boolean('is_visible')
                ->default(true)
                ->after('published_at')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('campaign_aars', function (Blueprint $table): void {
            $table->dropIndex(['is_visible']);
            $table->dropColumn('is_visible');
        });
    }
};
