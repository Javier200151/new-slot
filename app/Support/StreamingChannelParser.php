<?php

namespace App\Support;

class StreamingChannelParser
{
    public static function twitchLogin(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $parts = parse_url(trim($url));

        if (! is_array($parts)) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $host = preg_replace('/^www\./', '', $host);

        if ($host !== 'twitch.tv') {
            return null;
        }

        $path = trim((string) ($parts['path'] ?? ''), '/');
        $login = explode('/', $path)[0] ?? null;

        if (! $login || ! preg_match('/^[A-Za-z0-9_]{1,25}$/', $login)) {
            return null;
        }

        return $login;
    }

    public static function youtubeChannelIdFromUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        if (preg_match('~youtube\.com/channel/(UC[A-Za-z0-9_-]{20,})~i', trim($url), $match)) {
            return $match[1];
        }

        return null;
    }

    public static function youtubeChannelIdFromHtml(string $html): ?string
    {
        $patterns = [
            '~<meta[^>]+itemprop=["\']channelId["\'][^>]+content=["\'](UC[A-Za-z0-9_-]{20,})["\']~i',
            '~<meta[^>]+content=["\'](UC[A-Za-z0-9_-]{20,})["\'][^>]+itemprop=["\']channelId["\']~i',
            '~<link[^>]+rel=["\']alternate["\'][^>]+href=["\'][^"\']*feeds/videos\.xml\?channel_id=(UC[A-Za-z0-9_-]{20,})[^"\']*["\']~i',
            '~["\']channelId["\']\s*:\s*["\'](UC[A-Za-z0-9_-]{20,})["\']~',
            '~["\']externalId["\']\s*:\s*["\'](UC[A-Za-z0-9_-]{20,})["\']~',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $match)) {
                return $match[1];
            }
        }

        return null;
    }

    /**
     * Extrae un directo real de la página /live de un canal de YouTube.
     * La presencia de isLiveNow=true evita confundir estrenos programados o
     * vídeos históricos con una emisión que está ocurriendo en este momento.
     *
     * @return array{id:string,title:?string,started_at:?string}|null
     */
    public static function youtubeLiveFromHtml(string $html): ?array
    {
        if (! preg_match('~["\']isLiveNow["\']\s*:\s*true~i', $html)) {
            return null;
        }

        $videoId = null;

        $videoPatterns = [
            '~<link[^>]+rel=["\']canonical["\'][^>]+href=["\']https?://(?:www\.)?youtube\.com/watch\?v=([A-Za-z0-9_-]{6,20})[^"\']*["\']~i',
            '~<meta[^>]+property=["\']og:url["\'][^>]+content=["\']https?://(?:www\.)?youtube\.com/watch\?v=([A-Za-z0-9_-]{6,20})[^"\']*["\']~i',
            '~["\']videoId["\']\s*:\s*["\']([A-Za-z0-9_-]{6,20})["\']~',
        ];

        foreach ($videoPatterns as $pattern) {
            if (preg_match($pattern, $html, $match)) {
                $videoId = $match[1];
                break;
            }
        }

        if (! $videoId) {
            return null;
        }

        $title = null;
        $titlePatterns = [
            '~<meta[^>]+name=["\']title["\'][^>]+content=["\']([^"\']+)["\']~i',
            '~<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']~i',
            '~<title>(.*?)</title>~is',
        ];

        foreach ($titlePatterns as $pattern) {
            if (preg_match($pattern, $html, $match)) {
                $title = trim(html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5));
                $title = preg_replace('/\s*-\s*YouTube\s*$/i', '', $title);
                break;
            }
        }

        $startedAt = null;

        if (preg_match('~["\']startTimestamp["\']\s*:\s*["\']([^"\']+)["\']~', $html, $match)) {
            $startedAt = trim($match[1]);
        }

        return [
            'id' => $videoId,
            'title' => $title ?: null,
            'started_at' => $startedAt ?: null,
        ];
    }
}
