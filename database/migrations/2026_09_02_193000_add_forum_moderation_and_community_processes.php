<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_posts', function (Blueprint $table): void {
            $table->boolean('is_locked')->default(false)->after('is_pinned');
            $table->timestamp('locked_at')->nullable()->after('is_locked');
            $table->foreignId('locked_by')
                ->nullable()
                ->after('locked_at')
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::create('community_processes', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 32)->default('convocatoria')->index();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->boolean('applications_enabled')->default(true);
            $table->timestamp('applications_start_at')->nullable();
            $table->timestamp('applications_end_at')->nullable();
            $table->boolean('allow_application_edit')->default(true);
            $table->boolean('allow_application_withdraw')->default(true);
            $table->unsignedTinyInteger('max_winners')->nullable();
            $table->json('eligible_statuses')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('community_posts', function (Blueprint $table): void {
            $table->foreignId('community_process_id')
                ->nullable()
                ->unique()
                ->after('channel')
                ->constrained('community_processes')
                ->nullOnDelete();
        });

        Schema::create('community_process_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_process_id')
                ->constrained('community_processes')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['community_process_id', 'user_id'],
                'community_process_applications_process_user_unique'
            );
        });

        Schema::table('community_polls', function (Blueprint $table): void {
            $table->foreignId('community_process_id')
                ->nullable()
                ->unique()
                ->after('id')
                ->constrained('community_processes')
                ->nullOnDelete();
        });

        Schema::table('community_poll_options', function (Blueprint $table): void {
            $table->foreignId('candidate_user_id')
                ->nullable()
                ->after('community_poll_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('community_poll_options', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('candidate_user_id');
        });

        Schema::table('community_polls', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('community_process_id');
        });

        Schema::dropIfExists('community_process_applications');

        Schema::table('community_posts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('community_process_id');
        });

        Schema::dropIfExists('community_processes');

        Schema::table('community_posts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('locked_by');
            $table->dropColumn(['is_locked', 'locked_at']);
        });
    }
};
