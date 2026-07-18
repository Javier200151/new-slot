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
        if (Schema::hasTable('oper_slot') && Schema::hasColumn('oper_slot', 'externalslot_id')) {
            try {
                DB::statement('ALTER TABLE oper_slot DROP FOREIGN KEY oper_slot_externalslot_id_foreign');
            } catch (Throwable) {
                //
            }

            Schema::table('oper_slot', function (Blueprint $table) {
                $table->dropColumn('externalslot_id');
            });
        }

        Schema::dropIfExists('external_slot');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('external_slot', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        if (Schema::hasTable('oper_slot') && ! Schema::hasColumn('oper_slot', 'externalslot_id')) {
            Schema::table('oper_slot', function (Blueprint $table) {
                $table->foreignId('externalslot_id')
                    ->nullable()
                    ->after('group_id')
                    ->constrained('external_slot');
            });
        }
    }
};
