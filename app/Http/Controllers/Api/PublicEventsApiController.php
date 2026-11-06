<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventMedia;
use App\Models\EventResult;
use App\Models\Stream;
use App\Support\PublicApiData as Api;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicEventsApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $f = $request->validate(Api::eventRules());
        $query = Api::filterEvents(Api::events(), $f)
            ->with(['activity.activityType','activity.map','activity.platform','eventStatus']);
        Api::order($query, $f, ['fecha'=>'date','nombre'=>'name','recientes'=>'date'], 'date');
        return Api::pagination($request, $query, fn (Event $e) => Api::event($e));
    }

    public function show(int $event): JsonResponse
    {
        $item = Api::events()->with(['activity.activityType','eventStatus'])->findOrFail($event);
        return Api::one(Api::event($item, true));
    }

    public function orbat(int $event): JsonResponse
    {
        $item = Api::events()->findOrFail($event);
        return response()->json(['data' => Api::orbat($item)], 200, ['Cache-Control' => 'no-store, private']);
    }

    public function participants(Request $request, int $event): JsonResponse
    {
        $request->validate(Api::pageRules());
        $item = Api::events()->findOrFail($event);
        $slots = collect(Api::orbat($item)['grupos_visibles'])
            ->flatMap(fn ($g) => collect($g['slots'])->filter(fn ($s) => $s['ocupado'])->map(fn ($s) => [
                'nick_publico' => $s['ocupante']['nick'],
                'clan_publico' => $s['ocupante']['clan'],
                'grupo_orbat' => $g['nombre'], 'slot' => $s['nombre'],
                'url_perfil' => null,
            ]))->values();
        $profileNicks = \App\Models\User::query()->whereNotNull('promo_id')
            ->whereIn('nick', $slots->pluck('nick_publico')->filter()->unique())
            ->pluck('nick')->flip();
        $slots = $slots->map(function (array $row) use ($profileNicks): array {
            if ($row['clan_publico'] === 'Squad ALPHA' && $profileNicks->has($row['nick_publico'])) {
                $row['url_perfil'] = url('/usuarios/'.rawurlencode($row['nick_publico']));
            }
            return $row;
        });
        // Apply the same 100-item cap even on computed ORBAT collections.
        $size = max(1, min(100, (int) $request->input('per_page', 20)));
        $page = max(1, (int) $request->input('page', 1));
        $total = $slots->count();
        $base = $request->url();
        return response()->json([
            'data' => $slots->forPage($page, $size)->values()->all(),
            'meta' => ['pagina_actual'=>$page,'por_pagina'=>$size,'total'=>$total],
            'links' => [
                'anterior'=>$page>1 ? $request->fullUrlWithQuery(['page'=>$page-1]) : null,
                'siguiente'=>$page*$size<$total ? $request->fullUrlWithQuery(['page'=>$page+1]) : null,
            ],
        ]);
    }

    public function media(Request $request, int $event): JsonResponse
    {
        $request->validate(Api::pageRules());
        Api::events()->findOrFail($event);
        $items = EventMedia::query()->where('event_id', $event)
            ->whereIn('type', ['clip','vod'])->whereNotNull('url')->orderByDesc('id')->get()
            ->map(fn (EventMedia $m) => [
                'id'=>(int)$m->id, 'clase'=>$m->type, 'titulo'=>$m->title,
                'fuente'=>$m->provider, 'url_publica'=>$m->url,
                'miniatura_url'=>null, 'publicado_en'=>$m->created_at?->toIso8601String(),
            ]);
        $clips = \App\Models\CommunityClip::query()->where('event_id',$event)->where('is_visible',true)->get()
            ->map(fn ($clip) => [
                'id'=>(int)$clip->id, 'clase'=>'clip','titulo'=>$clip->title,
                'fuente'=>$clip->platform,'url_publica'=>$clip->url,
                'miniatura_url'=>$clip->thumbnail_url,
                'publicado_en'=>$clip->clip_created_at?->toIso8601String(),
            ]);
        $vods = Stream::query()->where('event_id',$event)->where('is_vod',true)
            ->where('visible_on_home',true)
            ->whereHas('streamer',fn (Builder $s)=>$s->where('enable',true))->get()
            ->map(fn (Stream $vod) => [
                'id'=>(int)$vod->id, 'clase'=>'vod','titulo'=>$vod->title,
                'fuente'=>$vod->platform,'url_publica'=>$vod->stream_url,
                'miniatura_url'=>$vod->thumbnail_url,
                'publicado_en'=>$vod->published_at?->toIso8601String(),
            ]);
        $all = $items->concat($clips)->concat($vods)
            ->filter(fn ($item)=>filled($item['url_publica']))
            ->unique('url_publica')->sortByDesc('publicado_en')->values();
        $size = max(1,min(100,(int)$request->input('per_page',20)));
        $page = max(1,(int)$request->input('page',1));
        return response()->json([
            'data'=>$all->forPage($page,$size)->values()->all(),
            'meta'=>['pagina_actual'=>$page,'por_pagina'=>$size,'total'=>$all->count()],
            'links'=>[
                'anterior'=>$page>1?$request->fullUrlWithQuery(['page'=>$page-1]):null,
                'siguiente'=>$page*$size<$all->count()?$request->fullUrlWithQuery(['page'=>$page+1]):null,
            ],
        ]);
    }

    public function result(int $event): JsonResponse
    {
        $item = Api::events()->whereHas('eventStatus', fn (Builder $q) => $q->where('name','FINALIZADO'))
            ->with(['eventResult','campaignAar'])->findOrFail($event);
        $aar = $item->campaignAar;
        $published = $aar && $aar->isPublished() && (bool) $aar->is_visible;
        abort_unless($item->eventResult || $published, 404);
        return Api::one([
            'evento_id'=>(int)$item->id,
            'estado_resultado_publico'=>$item->eventResult?->name,
            'resumen_publico'=>$published ? Api::aarSummary($aar) : null,
            'publicado_en'=>$published ? $aar->published_at?->toIso8601String() : null,
        ]);
    }
}
