<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('slot_types', function (Blueprint $table): void {
            $table->string('orbat_marking_permission', 12)->default('none');
        });

        Schema::create('event_orbat_markings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('participant_key', 191);
            $table->string('slot_key', 191);
            $table->unsignedSmallInteger('number')->nullable();
            $table->string('color', 10)->default('white');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['event_id', 'participant_key']);
        });

        Schema::create('event_orbat_marking_notices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('group_name', 191);
            $table->timestamp('created_at');
            $table->index(['user_id', 'event_id', 'created_at'], 'orbat_marking_notice_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_orbat_marking_notices');
        Schema::dropIfExists('event_orbat_markings');
        Schema::table('slot_types', function (Blueprint $table): void {
            $table->dropColumn('orbat_marking_permission');
        });
    }
};
