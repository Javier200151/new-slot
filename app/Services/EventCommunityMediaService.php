<?php

namespace App\Services;

use App\Models\CommunityClip;
use App\Models\Event;
use App\Models\EventMedia;
use App\Models\Stream;
use Illuminate\Support\Collection;

/** Read-only union of media submitted at the event and media linked later from Clips/VODs. */
class EventCommunityMediaService
{
    /** @return array{clips: Collection, vods: Collection} */
    public function forEvent(Event $event, Collection $original): array
    {
        $clips = $original->where('type', EventMedia::TYPE_CLIP)->values();
        $vods = $original->where('type', EventMedia::TYPE_VOD)->values();
        if ($event->eventStatus?->name !== 'FINALIZADO') {
            return ['clips' => $clips, 'vods' => $vods];
        }

        $keys = $original->mapWithKeys(fn (EventMedia $media) => [
            $this->key($media->type, $media->provider, (string) $media->external_id, (string) $media->url) => true,
        ])->all();

        foreach (CommunityClip::visible()->where('event_id', $event->id)->with('user')->get() as $clip) {
            $key = $this->key('clip', $clip->platform, $clip->external_id, $clip->url);
            if (isset($keys[$key])) continue;
            $keys[$key] = true;
            $media = new EventMedia([
                'event_id' => $event->id, 'user_id' => $clip->user_id, 'type' => EventMedia::TYPE_CLIP,
                'provider' => $clip->platform, 'url' => $clip->url, 'external_id' => $clip->external_id,
                'title' => $clip->title,
            ]);
            $media->setRelation('user', $clip->user);
            $media->setAttribute('community_clip_id', $clip->id);
            $media->created_at = $clip->created_at;
            $clips->push($media);
        }

        foreach (Stream::query()->where('event_id', $event->id)->where('visible_on_home', true)
            ->with('streamer.user')->get() as $stream) {
            if (! $stream->isVodForHome()) continue;
            $external = $this->vodId($stream);
            if (! $external) continue;
            $key = $this->key('vod', $stream->platform, $external, (string) $stream->stream_url);
            if (isset($keys[$key])) continue;
            $keys[$key] = true;
            $media = new EventMedia([
                'event_id' => $event->id, 'user_id' => $stream->streamer?->user_id,
                'type' => EventMedia::TYPE_VOD, 'provider' => $stream->platform,
                'url' => $stream->stream_url, 'external_id' => $external, 'title' => $stream->title,
            ]);
            $media->setRelation('user', $stream->streamer?->user);
            $media->setAttribute('linked_stream_id', $stream->id);
            $media->created_at = $stream->published_at ?? $stream->created_at;
            $vods->push($media);
        }
        return [
            'clips' => $clips->sortByDesc('created_at')->values(),
            'vods' => $vods->sortByDesc('created_at')->values(),
        ];
    }

    private function key(string $kind, string $provider, string $externalId, string $url): string
    {
        return strtolower($kind) . '|' . strtolower($provider) . '|' . ($externalId ?: $url);
    }

    private function vodId(Stream $stream): ?string
    {
        $url = (string) $stream->stream_url;
        if ($stream->platform === 'twitch') {
            return preg_match('~twitch\.tv/videos/(\d+)~i', $url, $m) ? $m[1] : null;
        }
        if ($stream->platform !== 'youtube') return null;
        $parts = parse_url($url);
        $path = trim((string) ($parts['path'] ?? ''), '/');
        if (preg_match('~^(?:shorts|live|embed)/([A-Za-z0-9_-]{11})$~', $path, $m)) return $m[1];
        if (($parts['host'] ?? '') === 'youtu.be' && preg_match('~^([A-Za-z0-9_-]{11})$~', $path, $m)) return $m[1];
        parse_str((string) ($parts['query'] ?? ''), $query);
        return preg_match('/^[A-Za-z0-9_-]{11}$/', (string) ($query['v'] ?? '')) ? $query['v'] : null;
    }
}
