<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('infrastructure_settings', function (Blueprint $table): void {
            $table->id();

            $table->string('arma3_academy_host')->nullable();
            $table->unsignedSmallInteger('arma3_academy_query_port')->nullable();

            $table->string('arma3_operations_host')->nullable();
            $table->unsignedSmallInteger('arma3_operations_query_port')->nullable();

            $table->string('reforger_academy_host')->nullable();
            $table->unsignedSmallInteger('reforger_academy_query_port')->nullable();

            $table->string('reforger_operations_host')->nullable();
            $table->unsignedSmallInteger('reforger_operations_query_port')->nullable();

            $table->boolean('ts3_enabled')->default(false);
            $table->string('ts3_host')->nullable();
            $table->unsignedSmallInteger('ts3_query_port')->default(10011);
            $table->unsignedSmallInteger('ts3_virtual_server_id')->default(1);
            $table->string('ts3_query_user')->nullable();
            $table->text('ts3_query_password')->nullable();

            $table->timestamps();
        });

        DB::table('infrastructure_settings')->insert([
            'ts3_enabled' => false,
            'ts3_query_port' => 10011,
            'ts3_virtual_server_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('infrastructure_settings');
    }
};
