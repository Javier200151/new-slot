<?php

use App\Models\MemberProcedureSetting;
use App\Services\MemberProcedures\MemberProcedureRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->pretending()
            || ! Schema::hasTable('member_procedure_settings')
            || ! Schema::hasTable('member_procedure_step_definitions')) {
            return;
        }

        $setting = MemberProcedureSetting::query()->first();
        if (! $setting) {
            $setting = MemberProcedureSetting::current();
        }

        app(MemberProcedureRegistry::class)->seedStoredDefinitions((int) $setting->id);
    }

    public function down(): void
    {
        if (! Schema::hasTable('member_procedure_step_definitions')) {
            return;
        }

        DB::table('member_procedure_step_definitions')
            ->where('procedure_type', 'not_promoted')
            ->delete();
    }
};
