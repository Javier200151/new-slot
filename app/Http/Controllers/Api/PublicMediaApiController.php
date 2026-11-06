<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClipYearContest;
use App\Models\ClipYearNomination;
use App\Models\CommunityClip;
use App\Models\CommunityClipComment;
use App\Models\Event;
use App\Models\HomepageNews;
use App\Models\Stream;
use App\Models\Streamer;
use App\Services\PublicLiveStreamService;
use App\Support\PublicApiData as Api;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicMediaApiController extends Controller
{
    public function clips(Request $request): JsonResponse
    {
        $f=$request->validate([
            'evento'=>'sometimes|integer|min:1','fuente'=>'sometimes|in:youtube,twitch',
            'protagonista'=>'sometimes|string|max:80',...Api::pageRules(),
        ]);
        $q=Api::clips()->with('user');
        if (isset($f['evento'])) {
            Api::events()->findOrFail($f['evento']);
            $q->where('event_id',$f['evento']);
        }
        // If a clip is attached to an event, it cannot reveal a draft event.
        $q->where(fn (Builder $scope)=>$scope->whereNull('event_id')->orWhereHas('event',fn (Builder $e)=>
            $e->whereHas('eventStatus',fn (Builder $s)=>$s->whereIn('name',Api::EVENT_STATES))));
        if (isset($f['fuente'])) $q->where('platform',$f['fuente']);
        if (isset($f['protagonista'])) $q->where(fn (Builder $s)=>
            $s->whereHas('protagonists',fn (Builder $u)=>$u->where('nick',$f['protagonista'])->whereNotNull('promo_id'))
            ->orWhereHas('protagonist',fn (Builder $u)=>$u->where('nick',$f['protagonista'])->whereNotNull('promo_id')));
        Api::order($q,$f,['recientes'=>'clip_created_at','fecha'=>'clip_created_at','nombre'=>'title'],'clip_created_at');
        return Api::pagination($request,$q,fn (CommunityClip $c)=>Api::clip($c));
    }

    public function clip(int $id): JsonResponse
    {
        $clip=Api::clips()->where(fn (Builder $s)=>$s->whereNull('event_id')->orWhereHas('event',fn (Builder $e)=>
            $e->whereHas('eventStatus',fn (Builder $t)=>$t->whereIn('name',Api::EVENT_STATES))))->findOrFail($id);
        return Api::one(Api::clip($clip,true));
    }

    public function comments(Request $request, int $id): JsonResponse
    {
        $request->validate(Api::pageRules());
        $this->clip($id); // 404 when the parent is not public
        $q=CommunityClipComment::query()->where('community_clip_id',$id)->with('user')->orderByDesc('id');
        return Api::pagination($request,$q,fn (CommunityClipComment $c)=>[
            'id_publico'=>(int)$c->id,'autor_nick'=>$c->user?->nick,
            'texto'=>(string)$c->body,'publicado_en'=>$c->created_at?->toIso8601String(),
        ]);
    }

    private function vodQuery(): Builder
    {
        return Stream::query()->where('is_vod',true)->where('visible_on_home',true)
            ->whereHas('streamer',fn (Builder $s)=>$s->where('enable',true))
            ->where(fn (Builder $s)=>$s->whereNull('event_id')->orWhereHas('event',fn (Builder $e)=>
                $e->whereHas('eventStatus',fn (Builder $status)=>$status->whereIn('name',Api::EVENT_STATES))));
    }

    private function vodRow(Stream $vod, bool $detailed=false): array
    {
        $vod->loadMissing(['streamer.user']);
        $row=['id'=>(int)$vod->id,'titulo'=>$vod->title ?: 'Grabación',
            'plataforma'=>$vod->platform,'url_video'=>$vod->stream_url,
            'miniatura_url'=>$vod->thumbnail_url,'publicado_en'=>$vod->published_at?->toIso8601String(),
            'streamer_nick'=>$vod->streamer?->user?->nick,'evento_id'=>$vod->event_id];
        if ($detailed) $row['descripcion_publica']=null;
        return $row;
    }

    public function vods(Request $request): JsonResponse
    {
        $request->validate(Api::pageRules());
        return Api::pagination($request,$this->vodQuery()->orderByDesc('published_at'),fn (Stream $s)=>$this->vodRow($s));
    }

    public function vod(int $id): JsonResponse
    {
        return Api::one($this->vodRow($this->vodQuery()->findOrFail($id),true));
    }

    public function streams(PublicLiveStreamService $service): JsonResponse
    {
        // Cache plain arrays: the production cache does not permit storing
        // serialized Eloquent models or Collections of models.
        $rows = \Illuminate\Support\Facades\Cache::remember('public_api_live_streams', now()->addSeconds(30),
            fn (): array => $service->active()->map(fn (Stream $s): array => [
                'streamer_nick' => $s->streamer?->user?->nick,
                'plataforma' => $s->platform,
                'titulo' => $s->title,
                'url_publica' => $s->stream_url,
                'en_directo' => (bool) $s->enabled,
                'inicio' => $s->started_at?->toIso8601String(),
            ])->values()->all());
        return response()->json(['data' => $rows]);
    }

    private function channelUrl(?string $channel, string $platform): ?string
    {
        $channel = trim((string) $channel);
        if ($channel === '') return null;
        if (preg_match('~^https://~i', $channel)) return $channel;
        if (str_contains($channel, '://') || str_starts_with($channel, '//')) return null;
        return $platform === 'twitch'
            ? 'https://www.twitch.tv/'.rawurlencode(ltrim($channel, '@/'))
            : 'https://www.youtube.com/'.ltrim($channel, '/');
    }

    public function streamers(): JsonResponse
    {
        return Api::collection(Streamer::query()->where('enable',true)->with('user')->orderBy('id')->get(),function (Streamer $s) {
            $platform = filled($s->twitch_channel)?'twitch':(filled($s->youtube_channel)?'youtube':null);
            return [
                'nick'=>$s->user?->nick,
                'plataforma_publica'=>$platform,
                'url_publica'=>$platform === 'twitch'
                    ? $this->channelUrl($s->twitch_channel,'twitch')
                    : ($platform === 'youtube' ? $this->channelUrl($s->youtube_channel,'youtube') : $this->channelUrl($s->website_url,'web')),
            ];
        });
    }

    public function editions(): JsonResponse
    {
        return Api::collection(ClipYearContest::query()->where('nomination_starts_at','<=',now())->orderByDesc('year')->get(),fn (ClipYearContest $c)=>[
            'id'=>(int)$c->id,'nombre_publico'=>'Clip Awards '.$c->year,
            'inicio_candidaturas'=>$c->nomination_starts_at?->toIso8601String(),
            'fin_candidaturas'=>$c->nomination_ends_at?->toIso8601String(),
            'inicio_votacion'=>$c->voting_starts_at?->toIso8601String(),
            'fin_votacion'=>$c->voting_ends_at?->toIso8601String(),
        ]);
    }

    public function winners(): JsonResponse
    {
        $contests=ClipYearContest::query()->where('voting_ends_at','<',now())
            ->orderByDesc('voting_ends_at')->limit(30)->get();
        $rows=[];
        foreach ($contests as $contest) {
            $nominations=ClipYearNomination::query()->where('contest_id',$contest->id)->where('is_approved',true)
                ->whereHas('clip',fn (Builder $q)=>$q->where('is_visible',true)
                    ->where(fn (Builder $s)=>$s->whereNull('event_id')->orWhereHas('event',fn (Builder $e)=>
                        $e->whereHas('eventStatus',fn (Builder $state)=>$state->whereIn('name',Api::EVENT_STATES)))))
                ->has('votes')
                ->with(['clip','user'])
                ->withAvg('votes','skulls')->withCount('votes')
                ->get()->sort(fn ($a,$b)=>($b->votes_avg_skulls<=>$a->votes_avg_skulls) ?: ($b->votes_count<=>$a->votes_count))
                ->take(3)->values();
            foreach ($nominations as $rank=>$entry) {
                $rows[]=['edicion'=>['id'=>$contest->id,'nombre'=>'Clip Awards '.$contest->year],
                    'puesto'=>$rank+1,'clip'=>Api::clip($entry->clip),
                    'autor_nick'=>$entry->user?->nick,
                    'puntuacion_media'=>round((float)$entry->votes_avg_skulls,2),
                    'votos'=>(int)$entry->votes_count];
            }
        }
        return response()->json(['data'=>$rows]);
    }

    private function newsQuery(): Builder
    {
        // The real /actualidad page requires a permitted member status. It has
        // no anonymous-visible posts, so never expose those rows through API.
        return HomepageNews::query()->whereRaw('1 = 0');
    }

    private function newsRow(HomepageNews $n, bool $detail=false): array
    {
        $row=['id'=>(int)$n->id,'titulo'=>$n->title,
            'resumen_publico'=>Api::text($n->excerpt ?: $n->body, 350),
            'imagen_url'=>Api::image($n->image),
            'categoria'=>$n->category_label,
            'publicado_en'=>$n->published_at?->toIso8601String(),
            'url'=>url('/actualidad#anuncio-'.$n->id)];
        if ($detail) $row['contenido_publico']=Api::text($n->body,10000);
        return $row;
    }

    public function news(Request $request): JsonResponse
    {
        $request->validate(Api::pageRules());
        return Api::pagination($request,$this->newsQuery()->orderByDesc('published_at')->orderByDesc('id'),fn (HomepageNews $n)=>$this->newsRow($n));
    }

    public function newsItem(int $id): JsonResponse
    {
        return Api::one($this->newsRow($this->newsQuery()->findOrFail($id),true));
    }
}
