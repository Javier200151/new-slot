<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('member_procedure_settings')
            && ! Schema::hasColumn('member_procedure_settings', 'treasury_spreadsheet_id')) {
            Schema::table('member_procedure_settings', function (Blueprint $table): void {
                $table->string('treasury_spreadsheet_id', 160)
                    ->nullable()
                    ->after('google_general_sheet_gid');
            });
        }

        if (Schema::hasTable('pages') && ! Schema::hasColumn('pages', 'template')) {
            Schema::table('pages', function (Blueprint $table): void {
                $table->string('template', 32)
                    ->default('content')
                    ->after('slug');
            });
        }

        if (! Schema::hasTable('pages')) {
            return;
        }

        $existing = DB::table('pages')->where('slug', 'tesoreria')->first();

        if ($existing) {
            if (Schema::hasColumn('pages', 'template')) {
                DB::table('pages')
                    ->where('id', $existing->id)
                    ->update([
                        'template' => 'treasury',
                        'updated_at' => now(),
                    ]);
            }

            return;
        }

        DB::table('pages')->insert([
            'title' => 'Tesorería',
            'slug' => 'tesoreria',
            'template' => 'treasury',
            'content' => "[b]Transparencia económica de Squad ALPHA.[/b]\n\nAquí puedes consultar el saldo agregado de las cuentas de Tesorería y los gastos registrados durante los últimos 12 meses. Los saldos, pagos y posibles deudas de cada miembro son privados y solo se muestran al propio usuario desde Mi perfil.",
            'is_published' => true,
            'created_by' => null,
            'updated_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('pages') && Schema::hasColumn('pages', 'template')) {
            DB::table('pages')
                ->where('template', 'treasury')
                ->update(['template' => 'content']);

            Schema::table('pages', function (Blueprint $table): void {
                $table->dropColumn('template');
            });
        }

        if (Schema::hasTable('member_procedure_settings')
            && Schema::hasColumn('member_procedure_settings', 'treasury_spreadsheet_id')) {
            Schema::table('member_procedure_settings', function (Blueprint $table): void {
                $table->dropColumn('treasury_spreadsheet_id');
            });
        }
    }
};
