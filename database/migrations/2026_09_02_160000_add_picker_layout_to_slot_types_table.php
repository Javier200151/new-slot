<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('slot_types', function (Blueprint $table): void {
            $table->unsignedTinyInteger('picker_column')
                ->default(1)
                ->after('description');

            $table->unsignedInteger('picker_order')
                ->default(0)
                ->after('picker_column');
        });

        $slotTypeIds = DB::table('slot_types')
            ->orderBy('name')
            ->pluck('id')
            ->values();

        foreach ($slotTypeIds as $index => $slotTypeId) {
            DB::table('slot_types')
                ->where('id', $slotTypeId)
                ->update([
                    'picker_column' => ($index % 4) + 1,
                    'picker_order' => (intdiv($index, 4) + 1) * 10,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('slot_types', function (Blueprint $table): void {
            $table->dropColumn([
                'picker_column',
                'picker_order',
            ]);
        });
    }
};
