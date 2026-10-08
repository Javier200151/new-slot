<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('member_procedure_settings')) {
            return;
        }

        if (! Schema::hasColumn('member_procedure_settings', 'treasury_private_status_ids')) {
            Schema::table('member_procedure_settings', function (Blueprint $table): void {
                $table->json('treasury_private_status_ids')
                    ->nullable()
                    ->after('treasury_spreadsheet_id');
            });
        }

        $activeStatusId = Schema::hasTable('status')
            ? DB::table('status')->whereRaw('UPPER(name) = ?', ['ACTIVO'])->value('id')
            : null;

        if ($activeStatusId) {
            DB::table('member_procedure_settings')
                ->whereNull('treasury_private_status_ids')
                ->update([
                    'treasury_private_status_ids' => json_encode([(int) $activeStatusId]),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('member_procedure_settings')
            && Schema::hasColumn('member_procedure_settings', 'treasury_private_status_ids')) {
            Schema::table('member_procedure_settings', function (Blueprint $table): void {
                $table->dropColumn('treasury_private_status_ids');
            });
        }
    }
};
