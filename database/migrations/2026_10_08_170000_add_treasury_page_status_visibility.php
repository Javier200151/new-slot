<?php

use App\Models\Status;
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

        if (! Schema::hasColumn('member_procedure_settings', 'treasury_page_status_ids')) {
            Schema::table('member_procedure_settings', function (Blueprint $table): void {
                $table->json('treasury_page_status_ids')
                    ->nullable()
                    ->after('treasury_private_status_ids');
            });
        }

        $activeStatusId = Schema::hasTable('status')
            ? Status::query()->whereRaw('UPPER(name) = ?', ['ACTIVO'])->value('id')
            : null;

        if ($activeStatusId) {
            DB::table('member_procedure_settings')
                ->whereNull('treasury_page_status_ids')
                ->update([
                    'treasury_page_status_ids' => json_encode([(int) $activeStatusId]),
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('member_procedure_settings')
            && Schema::hasColumn('member_procedure_settings', 'treasury_page_status_ids')) {
            Schema::table('member_procedure_settings', function (Blueprint $table): void {
                $table->dropColumn('treasury_page_status_ids');
            });
        }
    }
};
