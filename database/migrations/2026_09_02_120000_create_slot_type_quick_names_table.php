<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slot_type_quick_names', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('slot_type_id')
                ->constrained('slot_types')
                ->cascadeOnDelete();
            $table->string('category', 120);
            $table->string('name', 255);
            $table->string('shortcut', 12)->nullable();
            $table->boolean('is_course_student')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['slot_type_id', 'name']);
            $table->index(['slot_type_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slot_type_quick_names');
    }
};
