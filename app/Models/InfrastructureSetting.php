<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class InfrastructureSetting extends Model
{
    protected $fillable = [
        'arma3_academy_host',
        'arma3_academy_query_port',
        'arma3_operations_host',
        'arma3_operations_query_port',
        'reforger_academy_host',
        'reforger_academy_query_port',
        'reforger_operations_host',
        'reforger_operations_query_port',
        'ts3_enabled',
        'tsviewer_server_id',
        'extra_arma_servers',
        'arma_servers',
    ];

    protected function casts(): array
    {
        return [
            'arma3_academy_query_port' => 'integer',
            'arma3_operations_query_port' => 'integer',
            'reforger_academy_query_port' => 'integer',
            'reforger_operations_query_port' => 'integer',
            'ts3_enabled' => 'boolean',
            'tsviewer_server_id' => 'integer',
            'extra_arma_servers' => 'array',
            'arma_servers' => 'array',
        ];
    }

    // Las claves se guardan cifradas por servidor, nunca en los datos de estado públicos.
    public function setArmaServersAttribute(?array $servers): void
    {
        $old = $this->getRawOriginal('arma_servers');
        $oldServers = is_string($old) ? json_decode($old, true) : [];
        $oldServers = is_array($oldServers) ? $oldServers : [];
        $encrypted = [];
        foreach ($servers ?? [] as $index => $server) {
            if (! is_array($server)) continue;
            $password = trim((string) ($server['access_password'] ?? ''));
            $clear = (bool) ($server['clear_password'] ?? false);
            unset($server['clear_password']);
            unset($server['access_password']);
            // Conserva contraseñas sin cambios al editar otros campos del formulario.
            if ($clear) {
                unset($server['password_ciphertext']);
            } elseif ($password !== '') {
                $server['password_ciphertext'] = Crypt::encryptString($password);
            } elseif (! empty($server['password_ciphertext'])) {
                // Se conserva cuando el formulario deja el campo de contraseña en blanco.
            } elseif (isset($oldServers[$index]['password_ciphertext']) && $this->sameServer($oldServers[$index], $server)) {
                $server['password_ciphertext'] = $oldServers[$index]['password_ciphertext'];
            }
            $encrypted[] = $server;
        }
        $this->attributes['arma_servers'] = json_encode($encrypted, JSON_UNESCAPED_UNICODE);
    }

    private function sameServer(array $old, array $new): bool
    {
        return ($old['name'] ?? null) === ($new['name'] ?? null)
            && ($old['host'] ?? null) === ($new['host'] ?? null)
            && (string) ($old['game_port'] ?? '') === (string) ($new['game_port'] ?? '');
    }

    public function serverAccessPassword(int $index): ?string
    {
        $server = ($this->arma_servers ?? [])[$index] ?? [];
        $ciphertext = $server['password_ciphertext'] ?? null;
        if (! is_string($ciphertext) || $ciphertext === '') return null;
        try { return Crypt::decryptString($ciphertext); }
        catch (DecryptException) { return null; }
    }

    protected static function booted(): void
    {
        static::saved(function (): void {
            Cache::forget('public.infrastructure.status.v2');
        });

        static::deleted(function (): void {
            Cache::forget('public.infrastructure.status.v2');
        });
    }

    public static function current(): self
    {
        $setting = static::query()->firstOrCreate([], [
            'ts3_enabled' => false,
            'arma_servers' => static::defaultArmaServers(),
        ]);

        if ($setting->arma_servers === null) {
            $setting->forceFill([
                'arma_servers' => static::legacyArmaServers($setting),
            ])->save();
        }

        return $setting;
    }

    public static function defaultArmaServers(): array
    {
        return [
            [
                'name' => 'ArmA 3 Academia',
                'host' => null,
                'game_port' => null,
            ],
            [
                'name' => 'ArmA 3 Operativos',
                'host' => null,
                'game_port' => null,
            ],
            [
                'name' => 'ArmA Reforger Academia',
                'host' => null,
                'game_port' => null,
            ],
            [
                'name' => 'ArmA Reforger Operativos',
                'host' => null,
                'game_port' => null,
            ],
        ];
    }

    private static function legacyArmaServers(self $setting): array
    {
        $servers = [
            static::legacyServer(
                'ArmA 3 Academia',
                $setting->arma3_academy_host,
                $setting->arma3_academy_query_port,
            ),
            static::legacyServer(
                'ArmA 3 Operativos',
                $setting->arma3_operations_host,
                $setting->arma3_operations_query_port,
            ),
            static::legacyServer(
                'ArmA Reforger Academia',
                $setting->reforger_academy_host,
                $setting->reforger_academy_query_port,
            ),
            static::legacyServer(
                'ArmA Reforger Operativos',
                $setting->reforger_operations_host,
                $setting->reforger_operations_query_port,
            ),
        ];

        foreach (array_values($setting->extra_arma_servers ?? []) as $server) {
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

        return $servers;
    }

    private static function legacyServer(string $name, ?string $host, ?int $queryPort): array
    {
        return [
            'name' => $name,
            'host' => filled($host) ? trim((string) $host) : null,
            'game_port' => $queryPort !== null && $queryPort > 1
                ? $queryPort - 1
                : null,
        ];
    }
}
