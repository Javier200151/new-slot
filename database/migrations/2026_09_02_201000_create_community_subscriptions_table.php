<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('community_subscriptions')) {
            return;
        }

        Schema::create('community_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('subscribable_type');
            $table->unsignedBigInteger('subscribable_id');
            $table->timestamps();

            $table->index(
                ['subscribable_type', 'subscribable_id'],
                'community_subscriptions_subject_index'
            );
            $table->unique(
                ['user_id', 'subscribable_type', 'subscribable_id'],
                'community_subscriptions_user_subject_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_subscriptions');
    }
};
