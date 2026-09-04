<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_reactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('reactable_type', 120);
            $table->unsignedBigInteger('reactable_id');
            $table->string('reaction', 24);
            $table->timestamps();

            $table->index(
                ['reactable_type', 'reactable_id'],
                'community_reactions_target_index'
            );

            $table->unique(
                ['user_id', 'reactable_type', 'reactable_id'],
                'community_reactions_user_target_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_reactions');
    }
};
