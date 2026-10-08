<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('member_procedure_step_definitions')) {
            DB::table('member_procedure_step_definitions')
                ->where('instructions', 'like', '%NewSlot%')
                ->orderBy('id')
                ->chunkById(100, function ($rows): void {
                    foreach ($rows as $row) {
                        DB::table('member_procedure_step_definitions')
                            ->where('id', $row->id)
                            ->update([
                                'instructions' => str_replace('NewSlot', 'Squad ALPHA', (string) $row->instructions),
                            ]);
                    }
                });
        }

        if (Schema::hasTable('member_procedure_steps')) {
            DB::table('member_procedure_steps')
                ->whereNotNull('meta')
                ->orderBy('id')
                ->chunkById(100, function ($rows): void {
                    foreach ($rows as $row) {
                        $meta = json_decode((string) $row->meta, true);
                        if (! is_array($meta) || ! isset($meta['instructions']) || ! is_string($meta['instructions'])) {
                            continue;
                        }

                        $clean = str_replace('NewSlot', 'Squad ALPHA', $meta['instructions']);
                        if ($clean === $meta['instructions']) {
                            continue;
                        }

                        $meta['instructions'] = $clean;
                        DB::table('member_procedure_steps')
                            ->where('id', $row->id)
                            ->update(['meta' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
                    }
                });
        }
    }

    public function down(): void
    {
        // Cambio de marca visible: no reintroducimos el nombre anterior al revertir.
    }
};
