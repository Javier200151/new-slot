<?php

namespace App\Services;

use App\Models\Stream;

class StreamEmbedService
{
    public function supports(
        string $platform,
        string $url
    ): bool {
        return match ($platform) {
            'twitch' =>
                $this->extractTwitchChannel($url) !== null,

            'youtube' =>
                $this->extractYoutubeVideoId($url) !== null,

            default => false,
        };
    }

    public function embedUrl(
        Stream $stream
    ): ?string {
        return match ($stream->platform) {
            'twitch' =>
                $this->twitchEmbedUrl(
                    $stream->stream_url
                ),

            'youtube' =>
                $this->youtubeEmbedUrl(
                    $stream->stream_url
                ),

            default => null,
        };
    }

    private function twitchEmbedUrl(
        string $url
    ): ?string {
        $channel =
            $this->extractTwitchChannel($url);

        if ($channel === null) {
            return null;
        }

        $parent = parse_url(
            config('app.url'),
            PHP_URL_HOST
        );

        if (! $parent) {
            return null;
        }

        return 'https://player.twitch.tv/?'
            . http_build_query([
                'channel' => $channel,
                'parent' => $parent,
                'autoplay' => 'true',
                'muted' => 'true',
            ]);
    }

    private function youtubeEmbedUrl(
        string $url
    ): ?string {
        $videoId =
            $this->extractYoutubeVideoId($url);

        if ($videoId === null) {
            return null;
        }

        return sprintf(
            'https://www.youtube-nocookie.com/embed/%s'
            . '?autoplay=1&mute=1&rel=0',
            rawurlencode($videoId)
        );
    }

    private function extractTwitchChannel(
        string $url
    ): ?string {
        $parts = parse_url(trim($url));

        if (! is_array($parts)) {
            return null;
        }

        $host = strtolower(
            $parts['host'] ?? ''
        );

        $host = preg_replace(
            '/^www\./',
            '',
            $host
        );

        if ($host !== 'twitch.tv') {
            return null;
        }

        $path = trim(
            $parts['path'] ?? '',
            '/'
        );

        if ($path === '') {
            return null;
        }

        $segments = explode('/', $path);

        $channel = $segments[0] ?? null;

        if (! $channel) {
            return null;
        }

        $reserved = [
            'directory',
            'downloads',
            'jobs',
            'p',
            'search',
            'settings',
            'subscriptions',
            'videos',
        ];

        if (
            in_array(
                strtolower($channel),
                $reserved,
                true
            )
        ) {
            return null;
        }

        return $channel;
    }

    private function extractYoutubeVideoId(
        string $url
    ): ?string {
        $parts = parse_url(trim($url));

        if (! is_array($parts)) {
            return null;
        }

        $host = strtolower(
            $parts['host'] ?? ''
        );

        $host = preg_replace(
            '/^www\./',
            '',
            $host
        );

        /*
         * youtu.be/VIDEO_ID
         */
        if ($host === 'youtu.be') {
            $id = trim(
                $parts['path'] ?? '',
                '/'
            );

            return $this->validYoutubeId($id)
                ? $id
                : null;
        }

        if (
            ! in_array(
                $host,
                [
                    'youtube.com',
                    'm.youtube.com',
                ],
                true
            )
        ) {
            return null;
        }

        $path = trim(
            $parts['path'] ?? '',
            '/'
        );

        /*
         * youtube.com/watch?v=VIDEO_ID
         */
        if ($path === 'watch') {
            parse_str(
                $parts['query'] ?? '',
                $query
            );

            $id = $query['v'] ?? null;

            return $this->validYoutubeId($id)
                ? $id
                : null;
        }

        /*
         * youtube.com/live/VIDEO_ID
         * youtube.com/embed/VIDEO_ID
         */
        $segments = explode('/', $path);

        if (
            isset($segments[0], $segments[1])
            && in_array(
                $segments[0],
                ['live', 'embed'],
                true
            )
        ) {
            $id = $segments[1];

            return $this->validYoutubeId($id)
                ? $id
                : null;
        }

        return null;
    }

    private function validYoutubeId(
        ?string $id
    ): bool {
        if (! is_string($id)) {
            return false;
        }

        return (bool) preg_match(
            '/^[A-Za-z0-9_-]{6,20}$/',
            $id
        );
    }
}