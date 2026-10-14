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
    private const TSVIEWER_SERVER_URL = 'https://www.tsviewer.com/index.php';
    private const TSVIEWER_TREE_URL = 'https://www.tsviewer.com/ts3viewer.php';

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

        $services = [];

        foreach (array_values($settings->arma_servers ?? []) as $index => $server) {
            if (! is_array($server)) {
                continue;
            }

            $label = trim((string) ($server['name'] ?? ''));
            $host = trim((string) ($server['host'] ?? ''));
            $gamePort = (int) ($server['game_port'] ?? 0);

            if ($label === '') {
                continue;
            }

            $services[] = $this->gameStatus(
                'arma_server_' . $index,
                $label,
                $host,
                $gamePort > 0 ? $gamePort + 1 : null,
            );
        }

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

    protected function queryA2sInfo(string $host, int $port): ?array
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
            $response = Http::accept('text/html,*/*;q=0.8')
                ->withUserAgent('SquadAlpha/1.0 (+https://squadalpha.es)')
                ->timeout(5)
                ->get(self::TSVIEWER_SERVER_URL, [
                    'page' => 'ts_viewer',
                    'ID' => $serverId,
                    'newlanguage' => 'en',
                ]);

            $parsed = $this->parseTsViewerPage($response);

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
                'channels' => $parsed['online'] && ($parsed['players'] ?? 0) > 0 ? $this->tsViewerOccupiedChannels($serverId) : [],
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

    /** Retrieve the public viewer tree separately, without ever affecting the status counter. */
    private function tsViewerOccupiedChannels(int $serverId): array
    {
        try {
            $response = Http::accept('text/javascript,*/*;q=0.8')
                ->withUserAgent('SquadAlpha/1.0 (+https://squadalpha.es)')
                ->timeout(5)
                ->get(self::TSVIEWER_TREE_URL, ['ID' => $serverId]);

            if (! $response->successful()) {
                return [];
            }

            return $this->parseTsViewerTree($response->body(), $serverId);
        } catch (Throwable) {
            return [];
        }
    }

    /** TSViewer returns a JavaScript assignment containing escaped HTML, not JSON. */
    private function parseTsViewerTree(string $script, int $serverId): array
    {
        if (! preg_match('/TSV\.ViewerScript\.Data\[' . preg_quote((string) $serverId, '/') . '\]\[\x27html\x27\]\s*=\s*\x27(.*?)\x27\s*;/s', $script, $match)) {
            return [];
        }

        // Decode only JavaScript's escaped quote/backslash and basic whitespace sequences.
        $html = preg_replace_callback('/\\\\([\\\\\x27"nrt])/', static fn (array $m): string => match ($m[1]) {
            'n' => "\n", 'r' => "\r", 't' => "\t", default => $m[1],
        }, $match[1]);

        if (! is_string($html) || ! str_contains($html, 'tsv_user')) {
            return [];
        }

        // TSViewer emits flat tsv_level blocks; parse only channel/user rows.
        // No external JavaScript is executed and no HTML is sent to the browser.
        preg_match_all('/<div\s+class="tsv_level\s+tsv_(channel|user)\b([^>]*)"([^>]*)>.*?<div\s+class="tsv_content\s+tsv_depth_(\d+)"[^>]*>(.*?)<\/div>/s', $html, $rows, PREG_SET_ORDER);
        $parents = [];
        $channels = [];
        foreach ($rows as $row) {
            $type = $row[1];
            $depth = (int) $row[4];
            $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($row[5]), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
            if ($type === 'channel') {
                foreach (array_keys($parents) as $level) {
                    if ($level >= $depth) unset($parents[$level]);
                }
                if ($text !== '') $parents[$depth] = $text;
                continue;
            }
            $nickname = '';
            if (preg_match('/\bdata-client_nickname="([^"]*)"/', $row[3], $nm)) {
                $nickname = html_entity_decode($nm[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            $nickname = trim($nickname !== '' ? $nickname : $text);
            if ($nickname === '') continue;
            $parentDepth = -1;
            $parentName = 'Sin canal';
            foreach ($parents as $level => $name) {
                if ($level < $depth && $level >= $parentDepth) {
                    $parentDepth = $level;
                    $parentName = $name;
                }
            }
            $channels[$parentName][] = $nickname;
        }

        $result = [];
        foreach ($channels as $name => $users) {
            $result[] = ['name' => $name, 'users' => $users];
        }

        return $result;
    }

    private function parseTsViewerPage(Response $response): ?array
    {
        if (! $response->successful()) {
            return null;
        }

        $body = trim($response->body());

        if ($body === '') {
            return null;
        }

        // La ficha pública de TSViewer identifica de forma explícita el estado
        // y los contadores del servidor mediante estos IDs. Los usamos en lugar
        // de buscar palabras "online/offline" en toda la página, ya que TSViewer
        // también muestra textos históricos con ambas palabras.
        $status = $this->extractTsViewerElementText($body, 'regHeadStatusLabel');

        if ($status === null) {
            return null;
        }

        $status = strtolower(trim($status));

        if (! in_array($status, ['online', 'offline'], true)) {
            return null;
        }

        if ($status === 'offline') {
            return [
                'online' => false,
                'players' => null,
                'max_players' => null,
            ];
        }

        $players = $this->extractTsViewerInteger($body, 'virtualserver_realclientsonline');
        $maxPlayers = $this->extractTsViewerInteger($body, 'virtualserver_maxclients');

        return [
            'online' => true,
            'players' => $players,
            'max_players' => $maxPlayers,
        ];
    }

    private function extractTsViewerElementText(string $html, string $id): ?string
    {
        $quotedId = preg_quote($id, '/');

        if (
            preg_match(
                '/<[^>]*\bid=["\']' . $quotedId . '["\'][^>]*>(.*?)<\/[^>]+>/is',
                $html,
                $matches,
            ) !== 1
        ) {
            return null;
        }

        $value = html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function extractTsViewerInteger(string $html, string $id): ?int
    {
        $value = $this->extractTsViewerElementText($html, $id);

        if ($value === null || preg_match('/^\d{1,6}$/', $value) !== 1) {
            return null;
        }

        return (int) $value;
    }

}