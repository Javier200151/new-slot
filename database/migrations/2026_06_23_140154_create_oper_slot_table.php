<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('oper_slot', function (Blueprint $table) {
            $table->id();

            $table->foreignId('oper_id')->constrained('operations');

            $table->foreignId('slot_type_id')->constrained('slot_types');

            $table->foreignId('user_id')->nullable()->constrained('users');

            $table->string('name');

            $table->timestamps();

            $table->integer('display_order')->default(0);

            $table->foreignId('group_id')->nullable()->constrained('operation_slot_group');

            $table->foreignId('externalslot_id')->nullable()->constrained('external_slot');

            $table->integer('number')->nullable();

            $table->string('color')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oper_slot');
    }
};
