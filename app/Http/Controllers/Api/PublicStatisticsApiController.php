<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Support\PublicApiData as Api;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicStatisticsApiController extends Controller
{
    public function overview(): JsonResponse
    {
        return Api::one([
            'eventos_publicos_total'=>Api::events()->count(),
            'eventos_activos_total'=>Api::events()->whereHas('eventStatus',fn (Builder $s)=>$s->where('name','ACTIVO'))->count(),
            'miembros_directorio_total'=>Api::users()->count(),
            'clips_publicos_total'=>Api::clips()->where(fn (Builder $q)=>$q->whereNull('event_id')->orWhereHas('event',fn (Builder $e)=>
                $e->whereHas('eventStatus',fn (Builder $s)=>$s->whereIn('name',Api::EVENT_STATES))))->count(),
            'actualizado_en'=>now()->toIso8601String(),
        ]);
    }

    public function events(Request $request): JsonResponse
    {
        $f=$request->validate(['desde'=>'sometimes|date_format:Y-m-d',
            'hasta'=>'sometimes|date_format:Y-m-d|after_or_equal:desde',
            'tipo_actividad'=>'sometimes|integer|min:1']);
        $q=Api::events();
        Api::filterEvents($q,$f);
        $all=$q->with(['eventStatus','activity.activityType'])->get();
        return Api::one([
            'periodo'=>['desde'=>$f['desde']??null,'hasta'=>$f['hasta']??null],
            'total'=>$all->count(),
            'por_estado'=>$all->groupBy(fn (Event $e)=>(string)$e->eventStatus?->name)
                ->map(fn ($rows,$state)=>['nombre'=>$state,'total'=>$rows->count()])->values()->all(),
            'por_tipo_actividad'=>$all->groupBy(fn (Event $e)=>(string)$e->activity?->activityType?->name)
                ->filter(fn ($rows,$name)=>$name!=='')
                ->map(fn ($rows,$name)=>['nombre'=>$name,'total'=>$rows->count()])->values()->all(),
        ]);
    }

    public function users(): JsonResponse
    {
        $users=Api::users()->with(['status','sqaGroups'])->get();
        $groups=[];
        foreach ($users as $u) {
            foreach ($u->sqaGroups as $g) {
                if ($g->show_in_organization) {
                    $groups[$g->name]=($groups[$g->name]??0)+1;
                }
            }
        }
        return Api::one([
            'miembros_directorio_total'=>$users->count(),
            'por_estado_publico'=>$users->groupBy(fn ($u)=>(string)$u->status?->name)
                ->map(fn ($rows,$name)=>['estado'=>$name,'total'=>$rows->count()])->values()->all(),
            'por_grupo_publico'=>collect($groups)->map(fn ($total,$name)=>['grupo'=>$name,'total'=>$total])->values()->all(),
        ]);
    }

    public function activities(): JsonResponse
    {
        $activities=Api::activities()->with('activityType')->get();
        return Api::one([
            'actividades_publicas_total'=>$activities->count(),
            'eventos_publicos_total'=>Api::events()->count(),
            'por_tipo_publico'=>$activities->groupBy(fn ($a)=>(string)$a->activityType?->name)
                ->filter(fn ($rows,$name)=>$name!=='')
                ->map(fn ($rows,$name)=>['tipo'=>$name,'total'=>$rows->count()])->values()->all(),
        ]);
    }
}
