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
        Schema::table('users', function (Blueprint $table) {

            $table->foreign('promo_id')
                ->references('id')
                ->on('promo');

            $table->foreign('status_id')
                ->references('id')
                ->on('status');

            $table->foreign('created_by')
                ->references('id')
                ->on('users');

            $table->foreign('updated_by')
                ->references('id')
                ->on('users');
        });

        Schema::table('operations', function (Blueprint $table) {

            $table->foreign('operation_type_id')
                ->references('id')
                ->on('operations_type');

            $table->foreign('operation_status_id')
                ->references('id')
                ->on('operation_status');

            $table->foreign('campaign_id')
                ->references('id')
                ->on('campaign');

            $table->foreign('platform_id')
                ->references('id')
                ->on('platforms');

            $table->foreign('day_id')
                ->references('id')
                ->on('operation_day');

            $table->foreign('created_by')
                ->references('id')
                ->on('users');

            $table->foreign('updated_by')
                ->references('id')
                ->on('users');
        });

        Schema::table('oper_slot', function (Blueprint $table) {

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

            $table->foreign('externalslot_id')
                ->references('id')
                ->on('external_slot');
        });

        Schema::table('metopa_user', function (Blueprint $table) {

            $table->foreign('metopa_id')
                ->references('id')
                ->on('metopas');

            $table->foreign('user_id')
                ->references('id')
                ->on('users');

            $table->foreign('created_by')
                ->references('id')
                ->on('users');

            $table->foreign('updated_by')
                ->references('id')
                ->on('users');
        });

        Schema::table('metopas', function (Blueprint $table) {

            $table->foreign('created_by')
                ->references('id')
                ->on('users');

            $table->foreign('updated_by')
                ->references('id')
                ->on('users');
        });

        Schema::table('operation_slot_group', function (Blueprint $table) {

            $table->foreign('operation_id')
                ->references('id')
                ->on('operations');
        });

        Schema::table('slot_types_status', function (Blueprint $table) {

            $table->foreign('slot_type_id')
                ->references('id')
                ->on('slot_types');

            $table->foreign('status_id')
                ->references('id')
                ->on('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
