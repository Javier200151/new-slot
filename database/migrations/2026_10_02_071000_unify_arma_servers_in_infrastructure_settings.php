<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('infrastructure_settings')) {
            return;
        }

        if (! Schema::hasColumn('infrastructure_settings', 'arma_servers')) {
            Schema::table('infrastructure_settings', function (Blueprint $table): void {
                $table->json('arma_servers')->nullable();
            });
        }

        DB::table('infrastructure_settings')
            ->orderBy('id')
            ->get()
            ->each(function (object $row): void {
                if (filled($row->arma_servers ?? null)) {
                    return;
                }

                $servers = [
                    $this->legacyServer(
                        'ArmA 3 Academia',
                        $row->arma3_academy_host ?? null,
                        $row->arma3_academy_query_port ?? null,
                    ),
                    $this->legacyServer(
                        'ArmA 3 Operativos',
                        $row->arma3_operations_host ?? null,
                        $row->arma3_operations_query_port ?? null,
                    ),
                    $this->legacyServer(
                        'ArmA Reforger Academia',
                        $row->reforger_academy_host ?? null,
                        $row->reforger_academy_query_port ?? null,
                    ),
                    $this->legacyServer(
                        'ArmA Reforger Operativos',
                        $row->reforger_operations_host ?? null,
                        $row->reforger_operations_query_port ?? null,
                    ),
                ];

                $extras = json_decode((string) ($row->extra_arma_servers ?? ''), true);

                if (is_array($extras)) {
                    foreach ($extras as $server) {
                        if (! is_array($server)) {
                            continue;
                        }

                        $servers[] = [
                            'name' => trim((string) ($server['name'] ?? '')),
                            'host' => trim((string) ($server['host'] ?? '')),
                            'game_port' => filled($server['game_port'] ?? null)
                                ? (int) $server['game_port']
                                : null,
                        ];
                    }
                }

                DB::table('infrastructure_settings')
                    ->where('id', $row->id)
                    ->update([
                        'arma_servers' => json_encode(
                            $servers,
                            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                        ),
                    ]);
            });
    }

    public function down(): void
    {
        if (
            Schema::hasTable('infrastructure_settings')
            && Schema::hasColumn('infrastructure_settings', 'arma_servers')
        ) {
            Schema::table('infrastructure_settings', function (Blueprint $table): void {
                $table->dropColumn('arma_servers');
            });
        }
    }

    private function legacyServer(string $name, ?string $host, mixed $queryPort): array
    {
        $queryPort = filled($queryPort) ? (int) $queryPort : null;

        return [
            'name' => $name,
            'host' => filled($host) ? trim((string) $host) : null,
            'game_port' => $queryPort !== null && $queryPort > 1
                ? $queryPort - 1
                : null,
        ];
    }
};
