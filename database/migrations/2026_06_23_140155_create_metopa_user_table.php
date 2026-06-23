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
        Schema::create('metopa_user', function (Blueprint $table) {

            $table->foreignId('metopa_id')->constrained('metopas');

            $table->foreignId('user_id')->constrained('users');

            $table->date('assigned_at');

            $table->timestamps();

            $table->softDeletes();

            $table->foreignId('created_by')->nullable()->constrained('users');

            $table->foreignId('updated_by')->nullable()->constrained('users');

            $table->primary([
                'metopa_id',
                'user_id'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metopa_user');
    }
};
