<?php

namespace App\Services;

use App\Models\CommunityClip;
use App\Models\Streamer;
use App\Support\ClipImportWindow;
use App\Support\StreamingChannelParser;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class YoutubeClipImportService
{
    /**
     * YouTube Data API does not identify Shorts explicitly. We use public,
     * embeddable, non-live videos up to three minutes as short-form candidates.
     * A horizontal short video may therefore be included.
     *
     * @return array{streamers:int,created:int,skipped:int,errors:int}
     */
    public function run(): array
    {
        $result = ['streamers' => 0, 'created' => 0, 'skipped' => 0, 'errors' => 0];
        $streamers = Streamer::query()
            ->where('enable', true)
            ->where('auto_import_youtube_clips', true)
            ->where(fn ($query) => $query->whereNotNull('youtube_channel')->orWhereNotNull('youtube_channel_id'))
            ->get();

        if ($streamers->isEmpty()) return $result;

        $key = trim((string) config('services.youtube.api_key'));
        if ($key === '') {
            Log::warning('Importación YouTube: configura YOUTUBE_API_KEY para recuperar clips automáticamente.');
            $result['errors'] = $streamers->count();
            return $result;
        }

        foreach ($streamers as $streamer) {
            $result['streamers']++;
            try {
                $this->importStreamer($streamer, $key, $result);
            } catch (Throwable $exception) {
                $result['errors']++;
                Log::warning('No se pudieron importar clips de YouTube', [
                    'streamer_id' => $streamer->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $result;
    }

    private function importStreamer(Streamer $streamer, string $key, array &$result): void
    {
        $channelId = $this->channelId($streamer);
        if ($channelId === null) throw new RuntimeException('No se ha podido identificar el canal de YouTube.');

        $channel = $this->api('/channels', [
            'part' => 'contentDetails', 'id' => $channelId, 'maxResults' => 1,
        ], $key);
        $uploads = (string) data_get($channel, 'items.0.contentDetails.relatedPlaylists.uploads', '');
        if ($uploads === '') throw new RuntimeException('El canal no tiene una lista pública de vídeos.');

        $start = ClipImportWindow::start($streamer);
        $next = null;
        $checked = [];
        // The first run examines up to 1,000 uploads. Subsequent hourly runs
        // examine the 100 newest; repeat a full scan every two days.
        $cacheKey = 'clips.youtube.full-scan.' . $streamer->id . '.' . $streamer->clip_import_window;
        $fullScan = ! Cache::has($cacheKey);
        $maxPages = $fullScan ? 20 : 2;
        for ($page = 0; $page < $maxPages; $page++) {
            $params = ['part' => 'contentDetails', 'playlistId' => $uploads, 'maxResults' => 50];
            if ($next) $params['pageToken'] = $next;
            $listing = $this->api('/playlistItems', $params, $key);
            $items = $listing['items'] ?? [];
            $ids = [];
            $oldestReached = false;

            foreach ($items as $entry) {
                $videoId = (string) data_get($entry, 'contentDetails.videoId', '');
                $published = (string) (data_get($entry, 'contentDetails.videoPublishedAt') ?: data_get($entry, 'snippet.publishedAt', ''));
                if ($published !== '' && Carbon::parse($published)->lessThan($start)) {
                    $oldestReached = true;
                    continue;
                }
                if (preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId) && ! isset($checked[$videoId])) {
                    $checked[$videoId] = true;
                    if (CommunityClip::withTrashed()->where('platform', 'youtube')->where('external_id', $videoId)->exists()) {
                        $result['skipped']++;
                    } else {
                        $ids[] = $videoId;
                    }
                }
            }

            if ($ids) {
                $videos = $this->api('/videos', [
                    'part' => 'snippet,contentDetails,status,liveStreamingDetails',
                    'id' => implode(',', $ids),
                ], $key);
                foreach ($videos['items'] ?? [] as $video) {
                    $this->saveVideo($streamer, $channelId, $video, $start, $result);
                }
            }
            $next = $listing['nextPageToken'] ?? null;
            if ($oldestReached || ! $next || ! $items) break;
        }
        if ($fullScan) Cache::put($cacheKey, true, now()->addDays(2));
    }

    private function saveVideo(Streamer $streamer, string $channelId, array $item, $start, array &$result): void
    {
        $id = (string) ($item['id'] ?? '');
        $snippet = $item['snippet'] ?? [];
        $duration = $this->duration((string) data_get($item, 'contentDetails.duration', ''));
        if (! preg_match('/^[A-Za-z0-9_-]{11}$/', $id)
            || (string) ($snippet['channelId'] ?? '') !== $channelId
            || $duration === null || $duration < 1 || $duration > 180
            || data_get($item, 'status.privacyStatus') !== 'public'
            || data_get($item, 'status.embeddable') !== true
            || ($snippet['liveBroadcastContent'] ?? 'none') !== 'none'
            || ! empty($item['liveStreamingDetails'])
            || empty($snippet['publishedAt'])
            || Carbon::parse($snippet['publishedAt'])->lessThan($start)) {
            $result['skipped']++;
            return;
        }

        // The Twitch game filter has no YouTube equivalent. On YouTube we can
        // only check whether Arma is mentioned in title or description.
        if ($streamer->clip_import_mode === 'arma'
            && ! preg_match('/\b(?:arma\s*3|arma\s*reforger)\b/iu',
                (string) ($snippet['title'] ?? '') . ' ' . (string) ($snippet['description'] ?? ''))) {
            $result['skipped']++;
            return;
        }

        if (CommunityClip::withTrashed()->where('platform', 'youtube')->where('external_id', $id)->exists()) {
            $result['skipped']++;
            return;
        }

        CommunityClip::create([
            'user_id' => $streamer->user_id,
            'streamer_id' => $streamer->id,
            'platform' => 'youtube',
            'external_id' => $id,
            'source' => 'automatic',
            'title' => (string) ($snippet['title'] ?? 'Vídeo corto de YouTube'),
            'url' => 'https://www.youtube.com/shorts/' . $id,
            'thumbnail_url' => (string) (data_get($snippet, 'thumbnails.high.url') ?: data_get($snippet, 'thumbnails.medium.url') ?: 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg'),
            'broadcaster_name' => (string) ($snippet['channelTitle'] ?? ''),
            'duration_seconds' => $duration,
            'clip_created_at' => Carbon::parse($snippet['publishedAt']),
            'is_visible' => true,
        ]);
        $result['created']++;
    }

    private function duration(string $iso): ?int
    {
        if (! preg_match('/^P(?:([0-9]+)D)?T?(?:([0-9]+)H)?(?:([0-9]+)M)?(?:([0-9]+)S)?$/', $iso, $m)) {
            return null;
        }
        return ((int) ($m[1] ?? 0) * 86400) + ((int) ($m[2] ?? 0) * 3600)
            + ((int) ($m[3] ?? 0) * 60) + (int) ($m[4] ?? 0);
    }

    private function channelId(Streamer $streamer): ?string
    {
        $saved = trim((string) $streamer->youtube_channel_id);
        if (preg_match('/^UC[A-Za-z0-9_-]{20,}$/', $saved)) return $saved;

        $url = trim((string) $streamer->youtube_channel);
        $parts = parse_url($url);
        if (! is_array($parts) || ! in_array(strtolower((string) ($parts['host'] ?? '')),
                ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)
            || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['https'], true)) return null;

        $id = StreamingChannelParser::youtubeChannelIdFromUrl($url);
        if (! $id) {
            $id = Cache::remember('clips.youtube.channel.' . sha1($url), now()->addDays(7), function () use ($url) {
                $response = Http::timeout(10)->get($url);
                return $response->successful() ? StreamingChannelParser::youtubeChannelIdFromHtml($response->body()) : null;
            });
        }
        if (! $id || ! preg_match('/^UC[A-Za-z0-9_-]{20,}$/', $id)) return null;
        $streamer->forceFill(['youtube_channel_id' => $id])->save();
        return $id;
    }

    private function api(string $path, array $params, string $key): array
    {
        $response = Http::timeout(14)->get('https://www.googleapis.com/youtube/v3' . $path,
            array_merge($params, ['key' => $key]));
        if (! $response->successful()) throw new RuntimeException('YouTube devolvió HTTP ' . $response->status());
        return $response->json() ?: [];
    }
}
