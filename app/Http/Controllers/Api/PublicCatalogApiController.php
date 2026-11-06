<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Campaign;
use App\Models\CampaignAar;
use App\Models\Event;
use App\Models\EventStatus;
use App\Models\Faction;
use App\Models\GameMap;
use App\Models\Metopa;
use App\Models\Period;
use App\Models\Platform;
use App\Models\SlotType;
use App\Models\Status;
use App\Models\User;
use App\Support\PublicApiData as Api;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicCatalogApiController extends Controller
{
    public function activities(Request $request): JsonResponse
    {
        $f = $request->validate([
            'tipo_actividad'=>'sometimes|integer|min:1','plataforma'=>'sometimes|integer|min:1',
            'mapa'=>'sometimes|integer|min:1','campana'=>'sometimes|integer|min:1',
            'q'=>'sometimes|string|max:50', ...Api::pageRules(),
        ]);
        $q = Api::activities()->with(['activityType','platform','map']);
        foreach (['tipo_actividad'=>'activity_type_id','plataforma'=>'platform_id','mapa'=>'map_id','campana'=>'campaign_id'] as $filter=>$column) {
            if (isset($f[$filter])) $q->where($column,$f[$filter]);
        }
        if (isset($f['q'])) $q->where('name','like','%'.addcslashes($f['q'],'%_\\').'%');
        Api::order($q,$f,['nombre'=>'name','recientes'=>'id','fecha'=>'id'],'id');
        return Api::pagination($request,$q,fn (Activity $a) => Api::activity($a));
    }

    public function activity(int $id): JsonResponse
    {
        return Api::one(Api::activity(Api::activities()->findOrFail($id),true));
    }

    public function activityEvents(Request $request, int $id): JsonResponse
    {
        Api::activities()->findOrFail($id);
        $f = $request->validate(Api::eventRules());
        $q = Api::filterEvents(Api::events()->where('activity_id',$id),$f)->with('activity.activityType');
        Api::order($q,$f,['fecha'=>'date','nombre'=>'name','recientes'=>'date'],'date');
        return Api::pagination($request,$q,fn (Event $e)=>Api::event($e));
    }

    public function activityTypes(): JsonResponse
    {
        $ids = Api::activities()->distinct()->pluck('activity_type_id')->filter();
        return Api::collection(ActivityType::query()->whereIn('id',$ids)->orderBy('name')->get(),fn (ActivityType $t)=>[
            'id'=>(int)$t->id, 'nombre'=>$t->name,
            'descripcion_publica'=>Api::text($t->description,1500),'color'=>$t->color,
        ]);
    }

    public function eventStates(): JsonResponse
    {
        return Api::collection(EventStatus::query()->whereIn('name',['ACTIVO','FINALIZADO','CANCELADO','BORRADOR'])->orderBy('name')->get(),fn (EventStatus $s)=>[
            'id'=>(int)$s->id,'nombre'=>$s->name,'color_publico'=>null,
        ]);
    }

    public function userStates(): JsonResponse
    {
        return Api::collection(Status::query()->orderBy('name')->get(),fn (Status $s)=>[
            'id'=>(int)$s->id,'nombre'=>$s->name,'color_publico'=>$s->color,
        ]);
    }

    private function mapsQuery(): Builder
    {
        return GameMap::query()->whereHas('activities',fn (Builder $q)=>$q->whereHas('events',fn (Builder $e)=>$e->whereHas('eventStatus',fn (Builder $s)=>$s->whereIn('name',Api::EVENT_STATES))));
    }

    private function mapRow(GameMap $map, bool $detailed = false): array
    {
        $map->loadMissing('platform');
        $row = ['id'=>(int)$map->id,'nombre'=>$map->name,
            'plataforma'=>Api::named($map->platform),'imagen_url'=>Api::image($map->image)];
        if ($detailed) $row['descripcion_publica']=Api::text($map->description,3000);
        return $row;
    }

    public function maps(Request $request): JsonResponse
    {
        $f = $request->validate(['plataforma'=>'sometimes|integer|min:1','q'=>'sometimes|string|max:50',...Api::pageRules()]);
        $q = $this->mapsQuery()->with('platform');
        if (isset($f['plataforma'])) $q->where('platform_id',$f['plataforma']);
        if (isset($f['q'])) $q->where('name','like','%'.addcslashes($f['q'],'%_\\').'%');
        return Api::pagination($request,$q->orderBy('name'),fn (GameMap $m)=>$this->mapRow($m));
    }

    public function map(int $id): JsonResponse
    {
        return Api::one($this->mapRow($this->mapsQuery()->findOrFail($id),true));
    }

    public function platforms(): JsonResponse
    {
        $ids=Api::activities()->distinct()->pluck('platform_id')->filter();
        return Api::collection(Platform::query()->whereIn('id',$ids)->orderBy('name')->get(),fn (Platform $p)=>[
            'id'=>(int)$p->id,'nombre'=>$p->name,'imagen_url'=>Api::image($p->image),
        ]);
    }

    public function factions(): JsonResponse
    {
        // Only factions referenced by activities which have a public event.
        $ids = \Illuminate\Support\Facades\DB::table('activity_enemy_faction')
            ->whereIn('activity_id',Api::activities()->select('activities.id'))
            ->pluck('faction_id');
        return Api::collection(Faction::query()->whereIn('id',$ids)->with('side')->orderBy('name')->get(),fn (Faction $f)=>[
            'id'=>(int)$f->id,'nombre'=>$f->name,'bando_publico'=>$f->side?->name,'logo_url'=>null,
        ]);
    }

    public function slotTypes(): JsonResponse
    {
        return Api::collection(SlotType::query()->orderBy('name')->get(),fn (SlotType $s)=>[
            'id'=>(int)$s->id,'nombre'=>$s->name,
            'descripcion_publica'=>Api::text($s->description,700), 'color_publico'=>null,
        ]);
    }

    public function periods(): JsonResponse
    {
        $ids=Api::activities()->distinct()->pluck('period_id')->filter();
        return Api::collection(Period::query()->whereIn('id',$ids)->orderBy('name')->get(),fn (Period $p)=>[
            'id'=>(int)$p->id,'nombre'=>$p->name,'descripcion_publica'=>Api::text($p->description,1000),
        ]);
    }

    public function campaigns(Request $request): JsonResponse
    {
        $f=$request->validate(['q'=>'sometimes|string|max:50','desde'=>'sometimes|date_format:Y-m-d',
            'hasta'=>'sometimes|date_format:Y-m-d|after_or_equal:desde',...Api::pageRules()]);
        $q=Api::campaigns();
        if (isset($f['q'])) $q->where('name','like','%'.addcslashes($f['q'],'%_\\').'%');
        if (isset($f['desde']) || isset($f['hasta'])) {
            // The public events range is applied below to the campaign activity relation.
            $q->whereHas('activities.events',fn (Builder $e)=>$e
                ->whereHas('eventStatus',fn (Builder $s)=>$s->whereIn('name',Api::EVENT_STATES))
                ->when(isset($f['desde']),fn (Builder $q)=>$q->whereDate('date','>=',$f['desde']))
                ->when(isset($f['hasta']),fn (Builder $q)=>$q->whereDate('date','<=',$f['hasta'])));
        }
        return Api::pagination($request,$q->orderBy('name'),fn (Campaign $c)=>Api::campaign($c));
    }

    public function campaign(int $id): JsonResponse
    {
        return Api::one(Api::campaign(Api::campaigns()->findOrFail($id),true));
    }

    public function campaignEvents(Request $request,int $id): JsonResponse
    {
        Api::campaigns()->findOrFail($id);
        $f=$request->validate(Api::eventRules());
        $q=Api::filterEvents(Api::events()->whereHas('activity',fn (Builder $a)=>$a->where('campaign_id',$id)),$f)
            ->with('activity.activityType');
        Api::order($q,$f,['fecha'=>'date','nombre'=>'name','recientes'=>'date'],'date');
        return Api::pagination($request,$q,fn (Event $e)=>Api::event($e));
    }

    public function campaignAars(Request $request,int $id): JsonResponse
    {
        Api::campaigns()->findOrFail($id);
        $request->validate(Api::pageRules());
        $q=CampaignAar::query()->where('campaign_id',$id)->where('status','published')->where('is_visible',true)
            ->whereHas('event',fn (Builder $e)=>$e->whereHas('eventStatus',fn (Builder $s)=>$s->where('name','FINALIZADO')))
            ->with('event:id,name')->orderByDesc('published_at');
        return Api::pagination($request,$q,fn (CampaignAar $aar)=>[
            'id'=>(int)$aar->id, 'titulo'=>'AAR: '.($aar->event?->name ?: 'Evento '.$aar->event_id),
            'resumen_publico'=>Api::aarSummary($aar), 'evento_id'=>(int)$aar->event_id,
            'publicado_en'=>$aar->published_at?->toIso8601String(),
            'url'=>url('/campanas/'.$id.'/aar/'.$aar->event_id),
        ]);
    }

    private function metopaRow(Metopa $m, bool $detail=false): array
    {
        $row=['id'=>(int)$m->id,'nombre'=>$m->name,
            'descripcion_publica'=>Api::text($m->description,2500),
            'imagen_url'=>Api::image($m->image),'url'=>url('/metopas/'.rawurlencode($m->name))];
        if ($detail) $row['imagen_grande_url']=Api::image($m->image_large);
        return $row;
    }

    public function metopas(Request $request): JsonResponse
    {
        $request->validate(Api::pageRules());
        return Api::pagination($request,Metopa::query()->orderBy('name'),fn (Metopa $m)=>$this->metopaRow($m));
    }

    public function metopa(int $id): JsonResponse
    {
        return Api::one($this->metopaRow(Metopa::query()->findOrFail($id),true));
    }

    public function metopaMembers(Request $request,int $id): JsonResponse
    {
        $request->validate(Api::pageRules());
        $metopa=Metopa::query()->findOrFail($id);
        $q=Api::users()->whereHas('metopas',fn (Builder $m)=>$m->whereKey($metopa->id))
            ->with('status')->orderBy('nick');
        return Api::pagination($request,$q,fn (User $u)=>[
            'nick'=>$u->nick,'avatar_url'=>Api::image($u->image),
            'url_perfil'=>url('/usuarios/'.rawurlencode($u->nick)),
        ]);
    }
}
