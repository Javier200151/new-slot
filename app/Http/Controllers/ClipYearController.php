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
    private function active(Request $request): void
    {
        abort_unless($request->user()?->status?->name === 'ACTIVO' || $request->user()?->hasRole('admin'),403);
    }
    public function index(): View
    {
        $contest = ClipYearContest::current();
        abort_unless($contest,404);
        $phase = $contest->phase();
        $nominations = $contest->nominations()->where('is_approved',true)
            ->with(['user','clip' => fn ($q) => $q->with(['user','protagonist','event'])->withCount(['likes','comments'])])
            ->withCount('votes')->withAvg('votes','skulls')
            ->when($phase === 'results',fn ($q) => $q->orderByDesc('votes_avg_skulls')->orderByDesc('votes_count'),fn ($q) => $q->orderBy('id'))
            ->get();
        return view('clips.year',compact('contest','phase','nominations'));
    }
    public function nominate(Request $request, CommunityClipService $service): RedirectResponse
    {
        $this->active($request);
        $contest = ClipYearContest::current();
        abort_unless($contest && $contest->phase() === 'nominations',403);
        $data = $request->validate(['url'=>['required','url','max:600'],'description'=>['nullable','string','max:900']]);
        if ($contest->nominations()->where('user_id',$request->user()->id)->exists()) {
            throw ValidationException::withMessages(['url'=>'Ya has presentado un candidato este año.']);
        }
        $parsed = $service->parse($data['url']);
        $clip = CommunityClip::query()->where('platform',$parsed['platform'])->where('external_id',$parsed['external_id'])->first();
        if (! $clip) {
            if (CommunityClip::withTrashed()->where('platform',$parsed['platform'])->where('external_id',$parsed['external_id'])->exists()) {
                throw ValidationException::withMessages(['url'=>'Este clip fue retirado de Squad ALPHA.']);
            }
            $clip = $service->addManual($request->user(),$data['url'],null);
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
        $this->active($request);
        $contest = ClipYearContest::current();
        abort_unless($contest && $contest->phase() === 'voting'
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
