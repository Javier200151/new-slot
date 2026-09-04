<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('campaign')
            && ! Schema::hasColumn('campaign', 'editor_id')
        ) {
            Schema::table('campaign', function (Blueprint $table): void {
                $table->foreignId('editor_id')
                    ->nullable()
                    ->after('description')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('campaign_aars')) {
            Schema::create('campaign_aars', function (Blueprint $table): void {
                $table->id();

                $table->foreignId('campaign_id')
                    ->constrained('campaign')
                    ->cascadeOnDelete();

                $table->foreignId('event_id')
                    ->unique()
                    ->constrained('events')
                    ->cascadeOnDelete();

                $table->foreignId('commander_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->string('status', 20)->default('pending')->index();
                $table->json('sections');
                $table->json('orbat_snapshot')->nullable();
                $table->timestamp('published_at')->nullable();

                $table->foreignId('updated_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamps();

                $table->index(['campaign_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_aars');

        if (
            Schema::hasTable('campaign')
            && Schema::hasColumn('campaign', 'editor_id')
        ) {
            Schema::table('campaign', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('editor_id');
            });
        }
    }
};
