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
        Schema::create('operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operation_type_id')->constrained('operations_type');
            $table->foreignId('operation_status_id')->constrained('operations_type');
            $table->foreignId('campaign_id')->nullable()->constrained('campaign');
            $table->dateTime('date');
            $table->string('name');
            $table->string('image')->nullable();
            $table->longText('description')->nullable();
            $table->text('radio')->nullable();
            $table->boolean('ocap')->default(false);
            $table->string('ocap_url')->nullable();
            $table->boolean('respawn')->default(false);
            $table->boolean('jip')->default(false);
            $table->boolean('persistent')->default(false);
            $table->foreignId('day_id')->nullable()->constrained('operation_day');
            $table->string('pbo')->nullable();
            $table->text('addons')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operations');
    }
};
