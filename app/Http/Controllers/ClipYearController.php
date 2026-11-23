<?php

namespace App\Http\Controllers;

use App\Models\ClipYearContest;
use App\Models\ClipYearNomination;
use App\Models\ClipYearVote;
use App\Models\CommunityClip;
use App\Services\CommunityClipService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
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
            ->whereHas('clip', fn ($query) => $query->where('is_visible', true))
            ->with(['user','clip.user','clip.protagonist','clip.protagonists','clip.event'])
            ->withCount('votes')->withAvg('votes','skulls')
            ->when($phase === 'results',fn ($q) => $q->orderByDesc('votes_avg_skulls')->orderByDesc('votes_count'),fn ($q) => $q->orderBy('id'))
            ->get();
        $myNomination = $request->user()
            ? $contest->nominations()->with(['clip.protagonists', 'clip.protagonist', 'clip.user'])
                ->where('user_id', $request->user()->id)->first()
            : null;

        return view('clips.year', compact('contest', 'phase', 'nominations', 'myNomination'));
    }
    public function nominate(Request $request, CommunityClipService $service): RedirectResponse
    {
        $contest = ClipYearContest::current();
        abort_unless($contest && $contest->phase() === 'nominations' && $contest->allowsUser($request->user()),403);
        $data = $request->validate([
            'url'=>['required','url','max:600'],
            'description'=>['nullable','string','max:900'],
            'title'=>['nullable','string','max:255'],
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
            $clip = $service->addManual($request->user(),$data['url'],$data['title'] ?? null,$data['event_id'] ?? null,$protagonistIds);
        } else {
            if (! $clip->is_visible) {
                throw ValidationException::withMessages(['url' => 'Este clip no está disponible para el concurso.']);
            }
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
    /** Actualiza los datos del clip sin cambiar el vídeo ni los votos de la candidatura. */
    public function update(Request $request, ClipYearNomination $nomination, CommunityClipService $service): RedirectResponse
    {
        $contest = $nomination->contest;
        $clip = $nomination->clip;
        abort_unless($contest && $clip && ! $clip->trashed(), 404);

        $manager = $request->user()?->hasRole('admin') || $request->user()?->can('clip-contest.manage');
        $owner = (int) $nomination->user_id === (int) $request->user()?->id
            && (int) $clip->user_id === (int) $request->user()?->id
            && $contest->allowsUser($request->user());
        abort_unless($manager || ($owner && $contest->phase() === 'nominations'), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:900'],
            'event_id' => ['nullable', 'integer', 'exists:events,id,deleted_at,NULL'],
            'protagonist_user_ids' => ['nullable', 'string', 'max:255'],
        ]);
        $protagonists = $service->parseProtagonistIds($data['protagonist_user_ids'] ?? null);

        \Illuminate\Support\Facades\DB::transaction(function () use ($nomination, $clip, $service, $data, $protagonists): void {
            $service->updatePresentation($clip, trim($data['title']), $data['event_id'] ?? null, $protagonists);
            $nomination->update(['description' => $data['description'] ?? null]);
        });

        return back()->with('success', 'Los datos de tu candidatura se han actualizado.');
    }

    /** La retirada libera la plaza para presentar otro clip durante las candidaturas. */
    public function destroy(Request $request, ClipYearNomination $nomination): RedirectResponse
    {
        $contest = $nomination->contest;
        abort_unless($contest && (int) $nomination->user_id === (int) $request->user()?->id
            && $contest->phase() === 'nominations', 403);

        $nomination->delete();

        return back()->with('success', 'Candidatura retirada. Ya puedes presentar el mismo clip u otro.');
    }

    public function vote(Request $request, ClipYearNomination $nomination): RedirectResponse|JsonResponse
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
        if ($request->expectsJson()) {
            return response()->json([
                'skulls' => (int) $data['skulls'],
                'message' => 'Tu valoración se ha guardado.',
            ]);
        }

        return back()->with('success','Tu valoración se ha guardado.');
    }
}
