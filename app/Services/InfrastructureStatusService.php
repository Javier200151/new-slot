<?php

namespace App\Services;

use App\Models\InfrastructureSetting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class InfrastructureStatusService
{
    private const CACHE_KEY = 'public.infrastructure.status.v2';
    private const CACHE_SECONDS = 60;
    private const SOCKET_TIMEOUT_SECONDS = 1.0;
    private const TSVIEWER_WIDGET_URL = 'https://www.tsviewer.com/ts3viewer.php';

    public function snapshot(bool $force = false): array
    {
        if ($force) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(
            self::CACHE_KEY,
            now()->addSeconds(self::CACHE_SECONDS),
            fn (): array => $this->buildSnapshot(),
        );
    }

    private function buildSnapshot(): array
    {
        $settings = InfrastructureSetting::current();

        $services = [
            $this->gameStatus(
                'arma3_academy',
                'ArmA 3 Academia',
                $settings->arma3_academy_host,
                $settings->arma3_academy_query_port,
            ),
            $this->gameStatus(
                'arma3_operations',
                'ArmA 3 Operativos',
                $settings->arma3_operations_host,
                $settings->arma3_operations_query_port,
            ),
            $this->gameStatus(
                'reforger_academy',
                'ArmA Reforger Academia',
                $settings->reforger_academy_host,
                $settings->reforger_academy_query_port,
            ),
            $this->gameStatus(
                'reforger_operations',
                'ArmA Reforger Operativos',
                $settings->reforger_operations_host,
                $settings->reforger_operations_query_port,
            ),
        ];

        return [
            'services' => $services,
            'teamspeak' => $this->teamSpeakStatus($settings),
            'checked_at' => now()->toIso8601String(),
        ];
    }

    private function gameStatus(string $key, string $label, ?string $host, ?int $port): array
    {
        $host = trim((string) $host);
        $configured = $host !== '' && $port !== null && $port > 0;

        if (! $configured) {
            return [
                'key' => $key,
                'label' => $label,
                'configured' => false,
                'online' => false,
                'name' => null,
                'players' => null,
                'max_players' => null,
            ];
        }

        try {
            $info = $this->queryA2sInfo($host, $port);

            return [
                'key' => $key,
                'label' => $label,
                'configured' => true,
                'online' => $info !== null,
                'name' => $info['name'] ?? null,
                'players' => $info['players'] ?? null,
                'max_players' => $info['max_players'] ?? null,
            ];
        } catch (Throwable) {
            return [
                'key' => $key,
                'label' => $label,
                'configured' => true,
                'online' => false,
                'name' => null,
                'players' => null,
                'max_players' => null,
            ];
        }
    }

    private function queryA2sInfo(string $host, int $port): ?array
    {
        $errno = 0;
        $error = '';

        $socket = @stream_socket_client(
            "udp://{$host}:{$port}",
            $errno,
            $error,
            self::SOCKET_TIMEOUT_SECONDS,
            STREAM_CLIENT_CONNECT,
        );

        if (! is_resource($socket)) {
            return null;
        }

        try {
            stream_set_timeout($socket, 1, 0);

            $query = "\xFF\xFF\xFF\xFFTSource Engine Query\x00";
            fwrite($socket, $query);

            $response = fread($socket, 4096);

            if ($response === false || strlen($response) < 5) {
                return null;
            }

            if (substr($response, 0, 4) === "\xFF\xFF\xFF\xFF" && ord($response[4]) === 0x41 && strlen($response) >= 9) {
                $challenge = substr($response, 5, 4);
                fwrite($socket, $query . $challenge);
                $response = fread($socket, 4096);

                if ($response === false || strlen($response) < 5) {
                    return null;
                }
            }

            if (substr($response, 0, 4) === "\xFE\xFF\xFF\xFF") {
                return [
                    'name' => null,
                    'players' => null,
                    'max_players' => null,
                ];
            }

            if (substr($response, 0, 4) !== "\xFF\xFF\xFF\xFF" || ord($response[4]) !== 0x49) {
                return null;
            }

            return $this->parseA2sInfo($response);
        } finally {
            fclose($socket);
        }
    }

    private function parseA2sInfo(string $response): array
    {
        $offset = 6;

        $name = $this->readCString($response, $offset);
        $this->readCString($response, $offset);
        $this->readCString($response, $offset);
        $this->readCString($response, $offset);

        $offset += 2;

        $players = isset($response[$offset]) ? ord($response[$offset]) : null;
        $offset++;
        $maxPlayers = isset($response[$offset]) ? ord($response[$offset]) : null;

        return [
            'name' => $name !== '' ? $name : null,
            'players' => $players,
            'max_players' => $maxPlayers,
        ];
    }

    private function readCString(string $payload, int &$offset): string
    {
        $end = strpos($payload, "\x00", $offset);

        if ($end === false) {
            $value = substr($payload, $offset);
            $offset = strlen($payload);

            return $value;
        }

        $value = substr($payload, $offset, $end - $offset);
        $offset = $end + 1;

        return $value;
    }

    private function teamSpeakStatus(InfrastructureSetting $settings): array
    {
        if (! $settings->ts3_enabled) {
            return [
                'enabled' => false,
                'configured' => false,
                'available' => false,
                'online' => false,
                'players' => null,
                'max_players' => null,
                'provider' => 'tsviewer',
            ];
        }

        $serverId = (int) $settings->tsviewer_server_id;

        if ($serverId < 1) {
            return [
                'enabled' => true,
                'configured' => false,
                'available' => false,
                'online' => false,
                'players' => null,
                'max_players' => null,
                'provider' => 'tsviewer',
            ];
        }

        try {
            $response = Http::accept('text/html,application/javascript;q=0.9,*/*;q=0.8')
                ->withUserAgent('NewSlot/1.0 (+https://squadalpha.es)')
                ->timeout(4)
                ->get(self::TSVIEWER_WIDGET_URL, [
                    'ID' => $serverId,
                    'js' => 1,
                ]);

            $parsed = $this->parseTsViewerWidget($response);

            if ($parsed === null) {
                return [
                    'enabled' => true,
                    'configured' => true,
                    'available' => false,
                    'online' => false,
                    'players' => null,
                    'max_players' => null,
                    'provider' => 'tsviewer',
                ];
            }

            return [
                'enabled' => true,
                'configured' => true,
                'available' => true,
                'online' => $parsed['online'],
                'players' => $parsed['players'],
                'max_players' => $parsed['max_players'],
                'provider' => 'tsviewer',
            ];
        } catch (Throwable) {
            return [
                'enabled' => true,
                'configured' => true,
                'available' => false,
                'online' => false,
                'players' => null,
                'max_players' => null,
                'provider' => 'tsviewer',
            ];
        }
    }

    private function parseTsViewerWidget(Response $response): ?array
    {
        if (! $response->successful()) {
            return null;
        }

        $body = trim($response->body());

        if ($body === '') {
            return null;
        }

        $normalized = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = str_replace(
            ['\\n', '\\r', '\\t', '\\/', '\\"', "\\'"],
            [' ', ' ', ' ', '/', '"', "'"],
            $normalized,
        );

        // TSViewer suele incluir el estado en clases como
        // serverstatus_online / serverstatus_offline. Las comprobamos antes
        // de limpiar el HTML para no perder esa información.
        $offline = preg_match('/serverstatus[_-]?offline/i', $normalized) === 1;
        $online = preg_match('/serverstatus[_-]?online/i', $normalized) === 1;

        // Al eliminar etiquetas, insertamos espacios en su lugar. strip_tags()
        // uniría por ejemplo "online</div><span>7 / 32" como "online7 / 32",
        // impidiendo reconocer correctamente la palabra online.
        $plainText = preg_replace('/<[^>]*>/', ' ', $normalized) ?? $normalized;
        $plainText = preg_replace('/\s+/', ' ', $plainText) ?? $plainText;

        if (! $offline) {
            $offline = preg_match('/(?:server[\s_-]*)?status[\s_-]*[:=]?\s*offline|\bserver\s+offline\b|\boffline\b/i', $plainText) === 1;
        }

        if (! $online) {
            $online = preg_match('/(?:server[\s_-]*)?status[\s_-]*[:=]?\s*online|\bserver\s+online\b|\bonline\b/i', $plainText) === 1;
        }

        if (! $offline && ! $online) {
            return null;
        }

        $players = null;
        $maxPlayers = null;

        if (preg_match('/\b(\d{1,4})\s*\/\s*(\d{1,4})\b/', $normalized, $matches) === 1) {
            $players = (int) $matches[1];
            $maxPlayers = (int) $matches[2];
        } elseif (preg_match('/\b(\d{1,4})\s+(?:connected|clients?|users?)\b/i', $normalized, $matches) === 1) {
            $players = (int) $matches[1];
        }

        return [
            'online' => ! $offline && $online,
            'players' => $players,
            'max_players' => $maxPlayers,
        ];
    }
}
