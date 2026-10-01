<?php

namespace App\Services;

use App\Models\InfrastructureSetting;
use Illuminate\Support\Facades\Cache;
use Throwable;

class InfrastructureStatusService
{
    private const CACHE_KEY = 'public.infrastructure.status.v1';
    private const CACHE_SECONDS = 45;
    private const SOCKET_TIMEOUT_SECONDS = 1.0;

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

            // Algunos servidores exigen un challenge A2S antes de responder INFO.
            if (substr($response, 0, 4) === "\xFF\xFF\xFF\xFF" && ord($response[4]) === 0x41 && strlen($response) >= 9) {
                $challenge = substr($response, 5, 4);
                fwrite($socket, $query . $challenge);
                $response = fread($socket, 4096);

                if ($response === false || strlen($response) < 5) {
                    return null;
                }
            }

            // Una respuesta fragmentada sigue demostrando que el servidor responde,
            // aunque para el footer no necesitemos reconstruir todos los paquetes.
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
        $offset = 6; // cabecera (4), tipo I (1), protocolo (1)

        $name = $this->readCString($response, $offset);
        $this->readCString($response, $offset); // map
        $this->readCString($response, $offset); // folder
        $this->readCString($response, $offset); // game

        // app id uint16 little-endian
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
                'available' => false,
                'users' => [],
            ];
        }

        $host = trim((string) $settings->ts3_host);
        $port = (int) ($settings->ts3_query_port ?: 10011);
        $sid = (int) ($settings->ts3_virtual_server_id ?: 1);
        $username = trim((string) $settings->ts3_query_user);
        $password = (string) $settings->ts3_query_password;

        if ($host === '' || $port < 1 || $username === '' || $password === '') {
            return [
                'enabled' => true,
                'available' => false,
                'users' => [],
            ];
        }

        try {
            $users = $this->queryTeamSpeakUsers($host, $port, $sid, $username, $password);

            return [
                'enabled' => true,
                'available' => $users !== null,
                'users' => $users ?? [],
            ];
        } catch (Throwable) {
            return [
                'enabled' => true,
                'available' => false,
                'users' => [],
            ];
        }
    }

    private function queryTeamSpeakUsers(
        string $host,
        int $port,
        int $sid,
        string $username,
        string $password,
    ): ?array {
        $errno = 0;
        $error = '';

        $socket = @stream_socket_client(
            "tcp://{$host}:{$port}",
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

            $login = sprintf(
                "login client_login_name=%s client_login_password=%s\n",
                $this->escapeTeamSpeakValue($username),
                $this->escapeTeamSpeakValue($password),
            );

            fwrite($socket, $login);
            if (! $this->teamSpeakCommandSucceeded($socket)) {
                return null;
            }

            fwrite($socket, 'use sid=' . max(1, $sid) . "\n");
            if (! $this->teamSpeakCommandSucceeded($socket)) {
                return null;
            }

            fwrite($socket, "clientlist\n");
            $result = $this->readTeamSpeakCommand($socket);

            if (! $result['ok']) {
                return null;
            }

            fwrite($socket, "quit\n");

            $users = [];
            foreach ($result['data'] as $line) {
                foreach (explode('|', $line) as $record) {
                    $fields = $this->parseTeamSpeakRecord($record);

                    if (($fields['client_type'] ?? '1') !== '0') {
                        continue;
                    }

                    $nickname = trim((string) ($fields['client_nickname'] ?? ''));
                    if ($nickname !== '') {
                        $users[] = $nickname;
                    }
                }
            }

            natcasesort($users);

            return array_values(array_unique($users));
        } finally {
            fclose($socket);
        }
    }

    private function teamSpeakCommandSucceeded($socket): bool
    {
        return $this->readTeamSpeakCommand($socket)['ok'];
    }

    private function readTeamSpeakCommand($socket): array
    {
        $data = [];
        $deadline = microtime(true) + 2.0;

        while (! feof($socket) && microtime(true) < $deadline) {
            $line = fgets($socket);

            if ($line === false) {
                $meta = stream_get_meta_data($socket);
                if (($meta['timed_out'] ?? false) === true) {
                    break;
                }
                continue;
            }

            $line = trim($line);
            if ($line === '' || $line === 'TS3') {
                continue;
            }

            if (str_starts_with($line, 'error ')) {
                return [
                    'ok' => str_contains($line, 'id=0'),
                    'data' => $data,
                ];
            }

            // El saludo del ServerQuery puede quedar pendiente al abrir el socket.
            if (str_starts_with($line, 'Welcome to the TeamSpeak')) {
                continue;
            }

            $data[] = $line;
        }

        return ['ok' => false, 'data' => $data];
    }

    private function parseTeamSpeakRecord(string $record): array
    {
        $fields = [];

        foreach (preg_split('/\s+/', trim($record)) ?: [] as $pair) {
            if (! str_contains($pair, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $pair, 2);
            $fields[$key] = $this->unescapeTeamSpeakValue($value);
        }

        return $fields;
    }

    private function escapeTeamSpeakValue(string $value): string
    {
        return strtr($value, [
            '\\' => '\\\\',
            '/' => '\\/',
            ' ' => '\\s',
            '|' => '\\p',
            "\n" => '\\n',
            "\r" => '\\r',
            "\t" => '\\t',
        ]);
    }

    private function unescapeTeamSpeakValue(string $value): string
    {
        return strtr($value, [
            '\\s' => ' ',
            '\\p' => '|',
            '\\/' => '/',
            '\\n' => "\n",
            '\\r' => "\r",
            '\\t' => "\t",
            '\\\\' => '\\',
        ]);
    }
}
