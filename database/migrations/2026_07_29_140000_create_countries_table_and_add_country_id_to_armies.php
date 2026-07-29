<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('countries')) {
            Schema::create('countries', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('image')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('armies', 'country_id')) {
            Schema::table('armies', function (Blueprint $table) {
                $table->foreignId('country_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('countries')
                    ->nullOnDelete();
            });

            return;
        }

        Schema::table('armies', function (Blueprint $table) {
            $table->foreignId('country_id')
                ->nullable()
                ->change();
        });

        DB::table('armies')
            ->where('country_id', 0)
            ->update(['country_id' => null]);

        Schema::table('armies', function (Blueprint $table) {
            $table->foreign('country_id')
                ->references('id')
                ->on('countries')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('armies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('country_id');
        });

        Schema::dropIfExists('countries');
    }
};
