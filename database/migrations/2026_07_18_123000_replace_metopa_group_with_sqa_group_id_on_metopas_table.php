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
        Schema::table('metopas', function (Blueprint $table) {
            if (Schema::hasColumn('metopas', 'metopa_group')) {
                $table->dropColumn('metopa_group');
            }

            if (! Schema::hasColumn('metopas', 'sqa_group_id')) {
                $table->foreignId('sqa_group_id')
                    ->nullable()
                    ->after('despag2')
                    ->constrained('sqa_groups')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('metopas', function (Blueprint $table) {
            if (Schema::hasColumn('metopas', 'sqa_group_id')) {
                $table->dropConstrainedForeignId('sqa_group_id');
            }

            if (! Schema::hasColumn('metopas', 'metopa_group')) {
                $table->string('metopa_group')->nullable()->after('despag2');
            }
        });
    }
};
