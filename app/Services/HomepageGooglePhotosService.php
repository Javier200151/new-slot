<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class HomepageGooglePhotosService
{
    /**
     * Devuelve únicamente la última galería válida guardada en caché.
     *
     * IMPORTANTE: este método se usa durante la petición de la portada y no
     * realiza ninguna llamada HTTP a Google. Así la disponibilidad o latencia
     * de Google Fotos nunca afecta al TTFB de NewSlot.
     */
    public function latest(int $limit = 6, ?string $albumUrl = null): Collection
    {
        [$limit, $albumUrl] = $this->normaliseRequest($limit, $albumUrl);

        if ($albumUrl === '') {
            return collect();
        }

        $items = Cache::get($this->lastSuccessKey($albumUrl, $limit), []);

        return collect(is_array($items) ? $items : [])
            ->take($limit)
            ->values();
    }

    /**
     * Actualiza explícitamente la caché desde Google Fotos.
     *
     * Se invoca desde Artisan/scheduler, nunca desde la petición web de Inicio.
     * Si Google falla, conservamos indefinidamente la última lectura correcta.
     */
    public function refresh(int $limit = 6, ?string $albumUrl = null): Collection
    {
        [$limit, $albumUrl] = $this->normaliseRequest($limit, $albumUrl);

        if ($albumUrl === '') {
            return collect();
        }

        $fetched = $this->fetchMedia($albumUrl, $limit);

        if ($fetched !== []) {
            Cache::forever($this->lastSuccessKey($albumUrl, $limit), $fetched);

            return collect($fetched)->take($limit)->values();
        }

        return $this->latest($limit, $albumUrl);
    }

    private function normaliseRequest(int $limit, ?string $albumUrl): array
    {
        $limit = max(1, min(12, $limit));
        $albumUrl = trim((string) ($albumUrl ?: config('services.google_photos.album_url')));

        return [$limit, $albumUrl];
    }

    private function lastSuccessKey(string $albumUrl, int $limit): string
    {
        return 'homepage.google-photos.last-success.v2.' . sha1($albumUrl) . '.' . $limit;
    }

    private function fetchMedia(string $albumUrl, int $limit): array
    {
        try {
            $response = Http::connectTimeout(4)
                ->timeout(10)
                ->withHeaders([
                    'Accept' => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'es-ES,es;q=0.9,en;q=0.7',
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/152 Safari/537.36',
                ])
                ->get($albumUrl);

            if (! $response->successful()) {
                return [];
            }

            $html = $response->body();
            $items = $this->extractMediaFromHtml($html);
            $nextPageToken = $this->extractNextPageTokenFromHtml($html);

            if ($nextPageToken !== null) {
                $items = array_merge(
                    $items,
                    $this->fetchRemainingMedia($albumUrl, $html, $nextPageToken)
                );
            }

            return collect($items)
                ->unique('uid')
                // "Últimas" significa las últimas incorporadas al álbum. Si
                // Google no expusiera esa fecha, usamos la fecha del recurso.
                ->sortByDesc(fn (array $item): int => (int) ($item['album_add_date'] ?: $item['image_update_date']))
                ->take($limit)
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Los álbumes públicos de Google Fotos incluyen su carga inicial en
     * AF_initDataCallback(...). No dependemos de la posición exacta del bloque:
     * recorremos todos los callbacks y buscamos registros con la forma de un
     * elemento multimedia de Google Fotos.
     */
    private function extractMediaFromHtml(string $html): array
    {
        $items = [];

        foreach ($this->extractDataPayloads($html) as $payload) {
            $this->collectMediaItems($payload, $items);
        }

        return array_values($items);
    }

    private function extractNextPageTokenFromHtml(string $html): ?string
    {
        foreach ($this->extractDataPayloads($html) as $payload) {
            $candidateItems = [];
            $this->collectMediaItems($payload, $candidateItems);

            if ($candidateItems === []) {
                continue;
            }

            $token = $payload[2] ?? null;

            if (is_string($token) && $token !== '') {
                return $token;
            }
        }

        return null;
    }

    private function extractDataPayloads(string $html): array
    {
        if ($html === '' || ! str_contains($html, 'AF_initDataCallback')) {
            return [];
        }

        preg_match_all('/AF_initDataCallback\s*\(/', $html, $callbacks, PREG_OFFSET_CAPTURE);

        $payloads = [];

        foreach ($callbacks[0] ?? [] as $callback) {
            $start = (int) $callback[1] + strlen((string) $callback[0]);
            $end = $this->findCallbackEnd($html, $start);

            if ($end === null) {
                continue;
            }

            $callbackBody = substr($html, $start, $end - $start);
            $dataArray = $this->extractNamedArray($callbackBody, 'data');

            if ($dataArray === null) {
                continue;
            }

            $offset = 0;
            $parsed = $this->parseJavaScriptValue($dataArray, $offset);

            if (is_array($parsed)) {
                $payloads[] = $parsed;
            }
        }

        return $payloads;
    }

    private function fetchRemainingMedia(string $albumUrl, string $html, string $firstToken): array
    {
        $request = $this->resolveAlbumPageRequest($albumUrl, $html);

        if ($request === null) {
            return [];
        }

        $items = [];
        $seenTokens = [];
        $token = $firstToken;

        // 100 páginas es un límite defensivo muy por encima de lo necesario
        // para la galería actual y evita bucles si Google repitiese un token.
        for ($page = 2; $page <= 100 && $token !== ''; $page++) {
            if (isset($seenTokens[$token])) {
                break;
            }

            $seenTokens[$token] = true;
            $pageData = $this->fetchAlbumPage($request['album_key'], $request['auth_key'], $token);

            if ($pageData === null) {
                break;
            }

            $pageItems = [];
            $this->collectMediaItems($pageData['data'], $pageItems);
            $items = array_merge($items, array_values($pageItems));
            $token = $pageData['next_page_token'] ?? '';
        }

        return $items;
    }

    private function resolveAlbumPageRequest(string $albumUrl, string $html): ?array
    {
        $parts = parse_url($albumUrl);
        $albumKey = null;
        $authKey = null;

        if (is_array($parts)) {
            if (isset($parts['path']) && preg_match('~/share/([A-Za-z0-9_-]+)~', (string) $parts['path'], $match)) {
                $albumKey = $match[1];
            }

            if (isset($parts['query'])) {
                parse_str((string) $parts['query'], $query);
                $authKey = isset($query['key']) && is_string($query['key']) ? $query['key'] : null;
            }
        }

        // También soportamos un enlace corto que Google haya redirigido: en
        // ese caso recuperamos las claves del propio HTML ya resuelto.
        if ((! $albumKey || ! $authKey) && preg_match(
            '~snAcKc[^}]*?request:\s*\[\s*"([A-Za-z0-9_-]+)"\s*,\s*null\s*,\s*null\s*,\s*"([A-Za-z0-9_-]+)"~s',
            $html,
            $match
        )) {
            $albumKey = $match[1];
            $authKey = $match[2];
        }

        return $albumKey && $authKey
            ? ['album_key' => $albumKey, 'auth_key' => $authKey]
            : null;
    }

    private function fetchAlbumPage(string $albumKey, string $authKey, string $pageToken): ?array
    {
        try {
            $inner = json_encode([$albumKey, $pageToken, null, $authKey], JSON_UNESCAPED_SLASHES);
            $envelope = json_encode([[['snAcKc', $inner, null, 'generic']]], JSON_UNESCAPED_SLASHES);

            if (! is_string($inner) || ! is_string($envelope)) {
                return null;
            }

            $endpoint = 'https://photos.google.com/u/0/_/PhotosUi/data/batchexecute?'
                . http_build_query([
                    'rpcids' => 'snAcKc',
                    'source-path' => '/share/' . $albumKey,
                ]);

            $response = Http::connectTimeout(4)
                ->timeout(10)
                ->asForm()
                ->withHeaders([
                    'Accept' => '*/*',
                    'Origin' => 'https://photos.google.com',
                    'Referer' => 'https://photos.google.com/share/' . $albumKey . '?key=' . $authKey,
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/152 Safari/537.36',
                ])
                ->post($endpoint, ['f.req' => $envelope]);

            if (! $response->successful()) {
                return null;
            }

            return $this->parseBatchExecuteResponse($response->body());
        } catch (Throwable) {
            return null;
        }
    }

    private function parseBatchExecuteResponse(string $body): ?array
    {
        $text = ltrim($body);

        if (str_starts_with($text, ")]}'")) {
            $text = ltrim(substr($text, 4));
        }

        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = ltrim($line);

            if (! str_starts_with($line, '[[')) {
                continue;
            }

            $outer = json_decode($line, true);

            if (! is_array($outer)) {
                continue;
            }

            foreach ($outer as $entry) {
                if (! is_array($entry)
                    || ($entry[0] ?? null) !== 'wrb.fr'
                    || ($entry[1] ?? null) !== 'snAcKc'
                    || ! is_string($entry[2] ?? null)) {
                    continue;
                }

                $data = json_decode($entry[2], true);

                if (! is_array($data)) {
                    return null;
                }

                $nextToken = $data[2] ?? null;

                return [
                    'data' => $data,
                    'next_page_token' => is_string($nextToken) ? $nextToken : null,
                ];
            }
        }

        return null;
    }

    private function collectMediaItems(array $node, array &$items): void
    {
        if ($this->looksLikeMediaItem($node)) {
            $detail = $node[1];
            $uid = (string) $node[0];
            $baseUrl = html_entity_decode((string) $detail[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');

            // La URL extraída es el endpoint base de googleusercontent. Pedimos
            // una miniatura suficientemente grande y recortada para la cuadrícula.
            $items[$uid] = [
                'uid' => $uid,
                'image' => $this->sizedImageUrl($baseUrl),
                'width' => (int) $detail[1],
                'height' => (int) $detail[2],
                'image_update_date' => (int) $node[2],
                'album_add_date' => (int) $node[5],
            ];

            return;
        }

        foreach ($node as $child) {
            if (is_array($child)) {
                $this->collectMediaItems($child, $items);
            }
        }
    }

    private function looksLikeMediaItem(array $node): bool
    {
        if (count($node) < 6 || ! isset($node[0], $node[1], $node[2], $node[5])) {
            return false;
        }

        if (! is_string($node[0]) || ! str_starts_with($node[0], 'AF1Qip')) {
            return false;
        }

        if (! is_array($node[1]) || count($node[1]) < 3) {
            return false;
        }

        $url = $node[1][0] ?? null;
        $width = $node[1][1] ?? null;
        $height = $node[1][2] ?? null;

        return is_string($url)
            && preg_match('~^https://lh\d+\.googleusercontent\.com/~i', $url) === 1
            && is_numeric($width)
            && is_numeric($height)
            && is_numeric($node[2])
            && is_numeric($node[5]);
    }

    private function sizedImageUrl(string $url): string
    {
        $url = preg_replace('/=[^\/]+$/', '', trim($url)) ?: trim($url);

        return $url . '=w1200-h900-c';
    }

    /**
     * Extrae el array que sigue a una propiedad JS como "data:" respetando
     * arrays anidados y cadenas. Evita necesitar JSON5 o una dependencia npm.
     */
    private function extractNamedArray(string $source, string $name): ?string
    {
        $property = '(?:\"' . preg_quote($name, '/') . '\"|\'' . preg_quote($name, '/') . '\'|' . preg_quote($name, '/') . ')';

        if (! preg_match('/(?:^|[,\{\s])' . $property . '\s*:\s*/', $source, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $position = (int) $match[0][1] + strlen((string) $match[0][0]);
        $length = strlen($source);

        while ($position < $length && ctype_space($source[$position])) {
            $position++;
        }

        if ($position >= $length || $source[$position] !== '[') {
            return null;
        }

        $end = $this->findBalancedEnd($source, $position, '[', ']');

        return $end === null ? null : substr($source, $position, $end - $position + 1);
    }

    private function findCallbackEnd(string $source, int $start): ?int
    {
        $length = strlen($source);
        $depth = 0;
        $quote = null;
        $escaped = false;

        for ($i = $start; $i < $length; $i++) {
            $char = $source[$i];

            if ($quote !== null) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }

                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }

                if ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if ($char === '(' || $char === '[' || $char === '{') {
                $depth++;
                continue;
            }

            if ($char === '}' || $char === ']') {
                $depth = max(0, $depth - 1);
                continue;
            }

            if ($char === ')' && $depth === 0) {
                return $i;
            }
        }

        return null;
    }

    private function findBalancedEnd(string $source, int $start, string $open, string $close): ?int
    {
        $length = strlen($source);
        $depth = 0;
        $quote = null;
        $escaped = false;

        for ($i = $start; $i < $length; $i++) {
            $char = $source[$i];

            if ($quote !== null) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }

                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }

                if ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if ($char === $open) {
                $depth++;
            } elseif ($char === $close) {
                $depth--;

                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    /**
     * Parser mínimo para los valores de AF_initDataCallback. Soporta los tipos
     * que Google usa en "data": arrays, objetos, strings, números, booleanos,
     * null y tokens JS simples. Los tokens desconocidos se ignoran como null.
     */
    private function parseJavaScriptValue(string $source, int &$offset): mixed
    {
        $this->skipWhitespace($source, $offset);

        if ($offset >= strlen($source)) {
            return null;
        }

        return match ($source[$offset]) {
            '[' => $this->parseJavaScriptArray($source, $offset),
            '{' => $this->parseJavaScriptObject($source, $offset),
            '"', "'" => $this->parseJavaScriptString($source, $offset),
            default => $this->parseJavaScriptScalar($source, $offset),
        };
    }

    private function parseJavaScriptArray(string $source, int &$offset): array
    {
        $result = [];
        $offset++; // [
        $length = strlen($source);

        while ($offset < $length) {
            $this->skipWhitespace($source, $offset);

            if ($offset < $length && $source[$offset] === ']') {
                $offset++;
                break;
            }

            $result[] = $this->parseJavaScriptValue($source, $offset);
            $this->skipWhitespace($source, $offset);

            if ($offset < $length && $source[$offset] === ',') {
                $offset++;
            }
        }

        return $result;
    }

    private function parseJavaScriptObject(string $source, int &$offset): array
    {
        $result = [];
        $offset++; // {
        $length = strlen($source);

        while ($offset < $length) {
            $this->skipWhitespace($source, $offset);

            if ($offset < $length && $source[$offset] === '}') {
                $offset++;
                break;
            }

            $key = ($source[$offset] === '"' || $source[$offset] === "'")
                ? $this->parseJavaScriptString($source, $offset)
                : $this->parseIdentifier($source, $offset);

            $this->skipWhitespace($source, $offset);

            if ($offset < $length && $source[$offset] === ':') {
                $offset++;
            }

            $result[(string) $key] = $this->parseJavaScriptValue($source, $offset);
            $this->skipWhitespace($source, $offset);

            if ($offset < $length && $source[$offset] === ',') {
                $offset++;
            }
        }

        return $result;
    }

    private function parseJavaScriptString(string $source, int &$offset): string
    {
        $quote = $source[$offset++];
        $length = strlen($source);
        $result = '';

        while ($offset < $length) {
            $char = $source[$offset++];

            if ($char === $quote) {
                break;
            }

            if ($char !== '\\' || $offset >= $length) {
                $result .= $char;
                continue;
            }

            $escaped = $source[$offset++];

            $result .= match ($escaped) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'b' => "\b",
                'f' => "\f",
                'v' => "\v",
                '0' => "\0",
                '"' => '"',
                "'" => "'",
                '\\' => '\\',
                '/' => '/',
                'u' => $this->parseUnicodeEscape($source, $offset),
                'x' => $this->parseHexEscape($source, $offset),
                default => $escaped,
            };
        }

        return $result;
    }

    private function parseUnicodeEscape(string $source, int &$offset): string
    {
        $hex = substr($source, $offset, 4);

        if (strlen($hex) !== 4 || ! ctype_xdigit($hex)) {
            return 'u';
        }

        $offset += 4;
        $code = hexdec($hex);

        if ($code <= 0x7F) {
            return chr($code);
        }

        if ($code <= 0x7FF) {
            return chr(0xC0 | ($code >> 6))
                . chr(0x80 | ($code & 0x3F));
        }

        return chr(0xE0 | ($code >> 12))
            . chr(0x80 | (($code >> 6) & 0x3F))
            . chr(0x80 | ($code & 0x3F));
    }

    private function parseHexEscape(string $source, int &$offset): string
    {
        $hex = substr($source, $offset, 2);

        if (strlen($hex) !== 2 || ! ctype_xdigit($hex)) {
            return 'x';
        }

        $offset += 2;

        return chr(hexdec($hex));
    }

    private function parseJavaScriptScalar(string $source, int &$offset): mixed
    {
        $start = $offset;
        $length = strlen($source);

        while ($offset < $length && ! str_contains(",]}:\t\r\n ", $source[$offset])) {
            $offset++;
        }

        $token = trim(substr($source, $start, $offset - $start));

        return match ($token) {
            'true' => true,
            'false' => false,
            'null', 'undefined', 'NaN', 'Infinity', '-Infinity', '' => null,
            default => is_numeric($token) ? $token + 0 : $token,
        };
    }

    private function parseIdentifier(string $source, int &$offset): string
    {
        $start = $offset;
        $length = strlen($source);

        while ($offset < $length && preg_match('/[A-Za-z0-9_$]/', $source[$offset]) === 1) {
            $offset++;
        }

        return substr($source, $start, $offset - $start);
    }

    private function skipWhitespace(string $source, int &$offset): void
    {
        $length = strlen($source);

        while ($offset < $length && ctype_space($source[$offset])) {
            $offset++;
        }
    }
}
