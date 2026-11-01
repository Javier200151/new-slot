<?php

namespace App\Http\Controllers;

use App\Models\ClipYearContest;
use App\Models\ClipYearNomination;
use App\Models\ClipYearVote;
use App\Models\CommunityClip;
use App\Services\CommunityClipService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class ClipYearController extends Controller
{
    public function index(Request $request): View
    {
        // Una edición histórica concluida se consulta sin desplazar al
        // concurso actual ni habilitar de nuevo candidaturas o votaciones.
        $year = $request->query('year');
        if ($year !== null) {
            abort_unless(is_string($year) && preg_match('/^\d{4}$/', $year), 404);
            $contest = ClipYearContest::query()
                ->where('year', (int) $year)
                ->where('voting_ends_at', '<', now())
                ->first();
            abort_unless($contest, 404);
            $phase = 'results';
        } else {
            $contest = ClipYearContest::current();
            abort_unless($contest, 404);
            $phase = $contest->phase();
        }
        $nominations = $contest->nominations()->where('is_approved',true)
            ->with(['user','clip.user','clip.protagonist','clip.protagonists','clip.event'])
            ->withCount('votes')->withAvg('votes','skulls')
            ->when($phase === 'results',fn ($q) => $q->orderByDesc('votes_avg_skulls')->orderByDesc('votes_count'),fn ($q) => $q->orderBy('id'))
            ->get();
        return view('clips.year',compact('contest','phase','nominations'));
    }
    public function nominate(Request $request, CommunityClipService $service): RedirectResponse
    {
        $contest = ClipYearContest::current();
        abort_unless($contest && $contest->phase() === 'nominations' && $contest->allowsUser($request->user()),403);
        $data = $request->validate([
            'url'=>['required','url','max:600'],
            'description'=>['nullable','string','max:900'],
            'event_id'=>['nullable','integer','exists:events,id,deleted_at,NULL'],
            'protagonist_user_ids'=>['nullable','string','max:255'],
        ]);
        $protagonistIds = $service->parseProtagonistIds($data['protagonist_user_ids'] ?? null);
        if ($contest->nominations()->where('user_id',$request->user()->id)->exists()) {
            throw ValidationException::withMessages(['url'=>'Ya has presentado un candidato este año.']);
        }
        $parsed = $service->parse($data['url']);
        $clip = CommunityClip::query()->where('platform',$parsed['platform'])->where('external_id',$parsed['external_id'])->first();
        if (! $clip) {
            if (CommunityClip::withTrashed()->where('platform',$parsed['platform'])->where('external_id',$parsed['external_id'])->exists()) {
                throw ValidationException::withMessages(['url'=>'Este clip fue retirado de Squad ALPHA.']);
            }
            $clip = $service->addManual($request->user(),$data['url'],null,$data['event_id'] ?? null,$protagonistIds);
        } else {
            $service->supplementContext($clip, $data['event_id'] ?? null, $protagonistIds);
        }
        $contest->nominations()->create([
            'user_id'=>$request->user()->id,
            'community_clip_id'=>$clip->id,
            'description'=>$data['description'] ?? null,
            'is_approved'=>true,
        ]);
        return back()->with('success','Tu candidato se ha presentado.');
    }
    public function vote(Request $request, ClipYearNomination $nomination): RedirectResponse
    {
        $contest = ClipYearContest::current();
        abort_unless($contest && $contest->allowsUser($request->user()) && $contest->phase() === 'voting'
            && $nomination->contest_id === $contest->id && $nomination->is_approved && $nomination->clip()->exists(),403);
        $data = $request->validate(['skulls'=>['required','integer','between:1,5']]);
        if ((int) $nomination->user_id === (int) $request->user()->id) {
            throw ValidationException::withMessages(['skulls'=>'No puedes valorar tu propio candidato.']);
        }
        ClipYearVote::query()->updateOrCreate(
            ['nomination_id'=>$nomination->id,'user_id'=>$request->user()->id],
            ['skulls'=>$data['skulls']]
        );
        return back()->with('success','Tu valoración se ha guardado.');
    }
}
