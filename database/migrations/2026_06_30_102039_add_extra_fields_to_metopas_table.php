<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metopas', function (Blueprint $table) {
            if (! Schema::hasColumn('metopas', 'despag1')) {
                $table->text('despag1')->nullable()->after('image_large');
            }

            if (! Schema::hasColumn('metopas', 'despag2')) {
                $table->text('despag2')->nullable()->after('despag1');
            }

            if (! Schema::hasColumn('metopas', 'metopa_group')) {
                $table->string('metopa_group')->nullable()->after('despag2');
            }

            if (! Schema::hasColumn('metopas', 'imgback')) {
                $table->string('imgback')->nullable()->after('metopa_group');
            }
        });
    }

    public function down(): void
    {
        Schema::table('metopas', function (Blueprint $table) {
            if (Schema::hasColumn('metopas', 'imgback')) {
                $table->dropColumn('imgback');
            }

            if (Schema::hasColumn('metopas', 'metopa_group')) {
                $table->dropColumn('metopa_group');
            }

            if (Schema::hasColumn('metopas', 'despag2')) {
                $table->dropColumn('despag2');
            }

            if (Schema::hasColumn('metopas', 'despag1')) {
                $table->dropColumn('despag1');
            }
        });
    }
};