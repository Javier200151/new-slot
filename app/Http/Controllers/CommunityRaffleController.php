<?php

namespace App\Http\Controllers;

use App\Models\CommunityRaffle;
use App\Models\Status;
use App\Models\User;
use App\Services\CommunityRaffleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommunityRaffleController extends Controller
{
    public function __construct(private readonly CommunityRaffleService $service)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $raffles = CommunityRaffle::query()
            ->whereIn('status', ['active', 'closed'])
            ->when(! $this->service->canManage($user), fn ($query) => $query->whereHas(
                'entries', fn ($entries) => $entries->where('user_id', $user->id)
            ))
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->latest('id')->paginate(12);

        return view('community.raffles.index', compact('raffles'));
    }

    public function show(Request $request, CommunityRaffle $raffle): View
    {
        $canManage = $this->service->canManage($request->user());
        abort_unless($this->service->canView($raffle, $request->user()), 404);
        $state = $this->service->state($raffle);
        $managerUsers = collect();
        $statuses = collect();
        $selectedIds = [];
        if ($canManage && $raffle->status === 'active') {
            $managerUsers = User::query()->orderBy('nick')->get(['id', 'nick', 'status_id']);
            $statuses = Status::query()
                ->orderByRaw("CASE WHEN UPPER(name) = 'ACTIVO' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->get(['id', 'name']);
            $selectedIds = $raffle->entries()->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        }

        return view('community.raffles.show', compact(
            'raffle', 'state', 'canManage', 'managerUsers', 'statuses', 'selectedIds',
        ));
    }

    public function state(Request $request, CommunityRaffle $raffle): JsonResponse
    {
        abort_unless($this->service->canView($raffle, $request->user()), 404);

        return response()->json($this->service->state($raffle))
            ->header('Cache-Control', 'no-store, private, max-age=0');
    }

    public function participants(Request $request, CommunityRaffle $raffle): RedirectResponse
    {
        $this->assertManager($request);
        $validated = $request->validate([
            'user_ids' => ['array', 'max:2500'],
            'user_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->whereNull('deleted_at')],
        ]);
        $this->service->saveParticipants($raffle, $validated['user_ids'] ?? []);

        return back()->with('raffle_status', 'Participantes actualizados.');
    }

    public function spin(Request $request, CommunityRaffle $raffle): JsonResponse
    {
        $this->assertManager($request);
        $validated = $request->validate([
            'prize_id' => ['required', 'integer', Rule::exists('community_raffle_prizes', 'id')->where('raffle_id', $raffle->id)],
        ]);
        $this->service->spin($raffle, (int) $validated['prize_id'], $request->user());

        return response()->json($this->service->state($raffle))
            ->header('Cache-Control', 'no-store');
    }

    public function reroll(Request $request, CommunityRaffle $raffle, int $draw): RedirectResponse
    {
        $this->assertManager($request);
        $this->service->reroll($raffle, $draw, $request->user());

        return back()->with('raffle_status', 'Tirada anterior anulada. La ruleta está girando de nuevo.');
    }

    public function changePrize(Request $request, CommunityRaffle $raffle, int $draw): RedirectResponse
    {
        $this->assertManager($request);
        $validated = $request->validate([
            'prize_id' => ['required', 'integer', Rule::exists('community_raffle_prizes', 'id')->where('raffle_id', $raffle->id)],
        ]);
        $this->service->changePrize($raffle, $draw, (int) $validated['prize_id']);

        return back()->with('raffle_status', 'Premio actualizado. Los ganadores ya pueden ver el cambio.');
    }

    public function close(Request $request, CommunityRaffle $raffle): RedirectResponse
    {
        $this->assertManager($request);
        $this->service->close($raffle);

        return back()->with('raffle_status', 'Sorteo cerrado. Los ganadores ya se muestran en el apartado público.');
    }

    private function assertManager(Request $request): void
    {
        abort_unless($this->service->canManage($request->user()), 403);
    }
}
