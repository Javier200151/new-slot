<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('task_cards')) {
            Schema::create('task_cards', function (Blueprint $table): void {
                $table->id();
                $table->string('title', 160);
                $table->text('description')->nullable();
                $table->string('status', 24)->default('pending');
                $table->unsignedInteger('position')->default(0);
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['status', 'position'], 'task_cards_status_position_idx');
            });
        }

        if (! Schema::hasTable('task_card_user')) {
            Schema::create('task_card_user', function (Blueprint $table): void {
                $table->foreignId('task_card_id')->constrained('task_cards')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->primary(['task_card_id', 'user_id'], 'task_card_user_pk');
            });
        }

        if (! Schema::hasTable('task_checklist_items')) {
            Schema::create('task_checklist_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('task_card_id')->constrained('task_cards')->cascadeOnDelete();
                $table->string('label', 255);
                $table->boolean('completed')->default(false);
                $table->unsignedInteger('position')->default(0);
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['task_card_id', 'position'], 'task_checklist_card_position_idx');
            });
        }

        if (! Schema::hasTable('task_comments')) {
            Schema::create('task_comments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('task_card_id')->constrained('task_cards')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->text('body');
                $table->timestamps();

                $table->index(['task_card_id', 'created_at'], 'task_comments_card_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('task_checklist_items');
        Schema::dropIfExists('task_card_user');
        Schema::dropIfExists('task_cards');
    }
};
