<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('oper_slot')) {
            foreach ([
                'oper_slot_oper_id_foreign',
                'oper_slot_slot_type_id_foreign',
                'oper_slot_user_id_foreign',
                'oper_slot_group_id_foreign',
                'oper_slot_externalslot_id_foreign',
            ] as $foreignKey) {
                try {
                    DB::statement("ALTER TABLE oper_slot DROP FOREIGN KEY {$foreignKey}");
                } catch (Throwable) {
                    //
                }
            }
        }

        Schema::dropIfExists('oper_slot');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('oper_slot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('oper_id');
            $table->foreignId('slot_type_id');
            $table->foreignId('user_id')->nullable();
            $table->string('name');
            $table->timestamps();
            $table->integer('display_order')->default(0);
            $table->foreignId('group_id')->nullable();
            $table->integer('number')->nullable();
            $table->string('color')->nullable();

            $table->foreign('oper_id')
                ->references('id')
                ->on('operations');

            $table->foreign('slot_type_id')
                ->references('id')
                ->on('slot_types');

            $table->foreign('user_id')
                ->references('id')
                ->on('users');

            $table->foreign('group_id')
                ->references('id')
                ->on('operation_slot_group');
        });
    }
};
