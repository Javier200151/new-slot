<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Metopa;
use App\Models\SqaGroup;
use App\Models\User;
use App\Support\PublicApiData as Api;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicMembersApiController extends Controller
{
    private function memberByNick(string $nick): User
    {
        return Api::users()->where('nick', $nick)->firstOrFail();
    }

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'q'=>'sometimes|string|max:50', 'estado'=>'sometimes|string|max:40',
            'grupo'=>'sometimes|string|max:30', 'promocion'=>'sometimes|integer|min:1',
            ...Api::pageRules(),
        ]);
        $q = Api::users()->with(['status','promo','mainSqaGroup']);
        if (isset($f['q'])) $q->where('nick','like','%'.addcslashes($f['q'],'%_\\').'%');
        if (isset($f['estado'])) $q->whereHas('status',fn (Builder $s) => $s->where('name',$f['estado']));
        if (isset($f['promocion'])) $q->where('promo_id', $f['promocion']);
        if (isset($f['grupo'])) $q->whereHas('sqaGroups', fn (Builder $g) => $g
            ->where('name',$f['grupo'])->where('show_in_organization',true));
        Api::order($q, $f, ['nick'=>'nick','nombre'=>'nick','recientes'=>'id'], 'nick', 'asc');
        return Api::pagination($request, $q, fn (User $user) => Api::member($user));
    }

    /** Legacy /api/users/{identifier} remains a safe public alias; never return unpromoted users. */
    public function show(string $nick): JsonResponse
    {
        $user = $this->memberByNick($nick);
        return Api::one(Api::member($user, true));
    }

    public function legacy(string $identifier): JsonResponse
    {
        $query = Api::users();
        $user = ctype_digit($identifier) && strlen($identifier) === 17
            ? $query->where('steam_id', $identifier)->firstOrFail()
            : $query->where('nick', $identifier)->firstOrFail();
        return Api::one(Api::member($user, true));
    }

    public function metopas(Request $request, string $nick): JsonResponse
    {
        $request->validate(Api::pageRules());
        $user = $this->memberByNick($nick);
        $q = Metopa::query()->whereHas('users', fn (Builder $u) => $u->where('users.id', $user->id))
            ->orderBy('name');
        return Api::pagination($request, $q, fn (Metopa $m) => [
            'id'=>(int)$m->id, 'nombre'=>$m->name, 'nivel_publico'=>null,
            'imagen_url'=>Api::image($m->image),'url'=>url('/metopas/'.rawurlencode($m->name)),
        ]);
    }

    public function events(Request $request, string $nick): JsonResponse
    {
        $request->validate(Api::pageRules());
        $user = $this->memberByNick($nick);
        // Restrict to participation actually shown on the public ORBAT. A user
        // assigned to a hidden slot must not expose that event via attendance.
        $rows = Api::events()->whereHas('slots', fn (Builder $s) => $s->where('user_id', $user->id))
            ->with(['eventStatus','activity.activityType'])->orderByDesc('date')->get()
            ->map(function (Event $event) use ($user): ?array {
                foreach (Api::orbat($event)['grupos_visibles'] as $group) {
                    foreach ($group['slots'] as $slot) {
                        if (($slot['ocupante']['nick'] ?? null) === $user->nick
                            && ($slot['ocupante']['clan'] ?? null) === 'Squad ALPHA') {
                            return ['evento' => Api::event($event), 'slot_publico' => $slot['nombre']];
                        }
                    }
                }
                return null;
            })->filter()->values();
        $size = max(1, min(100, (int) $request->input('per_page', 20)));
        $page = max(1, (int) $request->input('page', 1));
        return response()->json([
            'data' => $rows->forPage($page, $size)->values()->all(),
            'meta' => ['pagina_actual' => $page, 'por_pagina' => $size, 'total' => $rows->count()],
            'links' => [
                'anterior' => $page > 1 ? $request->fullUrlWithQuery(['page' => $page-1]) : null,
                'siguiente' => $page*$size < $rows->count() ? $request->fullUrlWithQuery(['page' => $page+1]) : null,
            ],
        ]);
    }

    public function groups(Request $request): JsonResponse
    {
        $request->validate(Api::pageRules());
        $q = Api::groups()->orderBy('display_order')->orderBy('name');
        return Api::pagination($request, $q, fn (SqaGroup $g) => $this->groupRow($g));
    }

    private function groupRow(SqaGroup $g): array
    {
        return [
            'id'=>(int)$g->id, 'siglas'=>$g->name, 'nombre'=>$g->large_name ?: $g->name,
            'descripcion_publica'=>Api::text($g->description,1500), 'color'=>$g->color,
            'logo_url'=>Api::image($g->image), 'url'=>url('/usuarios?grupo='.$g->id),
        ];
    }

    public function group(int $id): JsonResponse
    {
        return Api::one($this->groupRow(Api::groups()->findOrFail($id)));
    }

    public function groupMembers(Request $request, int $id): JsonResponse
    {
        $request->validate(Api::pageRules());
        $group = Api::groups()->findOrFail($id);
        $q = Api::users()->whereHas('sqaGroups', fn (Builder $g) => $g->whereKey($group->id))
            ->with('status')->orderBy('nick');
        return Api::pagination($request, $q, fn (User $u) => [
            'nick'=>$u->nick, 'avatar_url'=>Api::image($u->image),
            'firma'=>Api::signature($u),
            'estado_publico'=>$u->status?->name,
            'url_perfil'=>url('/usuarios/'.rawurlencode($u->nick)),
        ]);
    }
}
