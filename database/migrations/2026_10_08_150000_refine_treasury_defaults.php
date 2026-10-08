<?php

use App\Models\MemberProcedureSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('member_procedure_settings')
            && Schema::hasColumn('member_procedure_settings', 'treasury_spreadsheet_id')) {
            DB::table('member_procedure_settings')
                ->where(function ($query): void {
                    $query->whereNull('treasury_spreadsheet_id')
                        ->orWhere('treasury_spreadsheet_id', '');
                })
                ->update([
                    'treasury_spreadsheet_id' => MemberProcedureSetting::DEFAULT_TREASURY_SPREADSHEET_ID,
                    'updated_at' => now(),
                ]);
        }

        if (! Schema::hasTable('pages')) {
            return;
        }

        $page = DB::table('pages')->where('slug', 'tesoreria')->first();
        if (! $page) {
            return;
        }

        $sentence = 'Aquí puedes consultar el saldo agregado de las cuentas de Tesorería y los gastos registrados durante los últimos 12 meses. Los saldos, pagos y posibles deudas de cada miembro son privados y solo se muestran al propio usuario desde Mi perfil.';
        $content = (string) ($page->content ?? '');
        if (str_contains($content, $sentence)) {
            $content = trim(str_replace(["\n\n{$sentence}", $sentence], '', $content));

            DB::table('pages')->where('id', $page->id)->update([
                'content' => $content,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // No se restaura texto editorial ni se borra el ID para no pisar cambios manuales posteriores.
    }
};
