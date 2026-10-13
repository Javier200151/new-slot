<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sqa_group_roles')) {
            Schema::create('sqa_group_roles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('sqa_group_id')->constrained('sqa_groups')->cascadeOnDelete();
                $table->unsignedBigInteger('role_id');
                $table->timestamps();

                $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
                $table->unique(['sqa_group_id', 'role_id'], 'sqa_group_roles_group_role_uq');
            });
        }

        if (! Schema::hasTable('sqa_group_role_sources')) {
            Schema::create('sqa_group_role_sources', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('sqa_group_id')->constrained('sqa_groups')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedBigInteger('role_id');
                $table->timestamps();

                $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
                $table->unique(['sqa_group_id', 'user_id', 'role_id'], 'sqa_group_role_sources_uq');
                $table->index(['user_id', 'role_id'], 'sqa_group_role_sources_user_role_idx');
            });
        }

        if (! Schema::hasTable('sqa_group_role_ownerships')) {
            Schema::create('sqa_group_role_ownerships', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedBigInteger('role_id');
                $table->boolean('was_preexisting')->default(false);
                $table->timestamps();

                $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
                $table->unique(['user_id', 'role_id'], 'sqa_group_role_ownerships_user_role_uq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sqa_group_role_ownerships');
        Schema::dropIfExists('sqa_group_role_sources');
        Schema::dropIfExists('sqa_group_roles');
    }
};
