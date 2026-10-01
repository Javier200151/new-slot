<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('event_media', 'file_path')) {
            return;
        }

        Schema::table('event_media', function (Blueprint $table): void {
            $table->string('file_path', 500)->nullable()->after('url');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('event_media', 'file_path')) {
            return;
        }

        Schema::table('event_media', function (Blueprint $table): void {
            $table->dropColumn('file_path');
        });
    }
};
