<?php

namespace App\Http\Controllers;

use App\Models\CommunityRouletteRoom;
use App\Models\Event;
use App\Models\User;
use App\Services\CommunityRouletteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommunityRouletteController extends Controller
{
    public function __construct(private readonly CommunityRouletteService $roulette)
    {
    }

    public function index(Request $request): View
    {
        $this->assertCanView($request);

        $currentRoom = $this->roulette->currentDisplayRoom();
        $history = $this->roulette->historyQuery();

        if ($currentRoom) {
            $history->where('id', '!=', $currentRoom->id);
        }

        return view('community.roulette.index', [
            'currentRoom' => $currentRoom,
            'history' => $history->paginate(12),
            'canManage' => $this->roulette->canManage($request->user()),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $this->assertCanManage($request);

        if ($active = CommunityRouletteRoom::query()->where('active_key', 1)->first()) {
            return redirect()
                ->route('community.roulette.show', $active)
                ->with('roulette_status', 'Ya existe una sala activa.');
        }

        $events = $this->roulette->eligibleEvents();
        $selectedEventId = (int) $request->integer('event_id');
        $selectedEvent = $events->firstWhere('id', $selectedEventId)
            ?: $events->first();

        $previousEvents = $selectedEvent
            ? $this->roulette->previousEventOptions($selectedEvent)
            : collect();
        $defaultPreviousEventIds = $selectedEvent
            ? $this->roulette->defaultPreviousEventIds($selectedEvent)
            : [];
        $targetSlots = $selectedEvent
            ? $this->roulette->availableTargetSlots($selectedEvent)
            : [];

        return view('community.roulette.create', compact(
            'events',
            'selectedEvent',
            'previousEvents',
            'defaultPreviousEventIds',
            'targetSlots',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->assertCanManage($request);

        $validated = $request->validate([
            'event_id' => ['required', 'integer', Rule::exists('events', 'id')->whereNull('deleted_at')],
            'target_slot_key' => ['required', 'string', 'size:26'],
            'previous_event_ids' => ['nullable', 'array', 'max:3'],
            'previous_event_ids.*' => ['nullable', 'integer', 'distinct', Rule::exists('events', 'id')->whereNull('deleted_at')],
        ]);

        $event = Event::query()->findOrFail((int) $validated['event_id']);
        $room = $this->roulette->createRoom(
            $request->user(),
            $event,
            (string) $validated['target_slot_key'],
            array_values(array_filter($validated['previous_event_ids'] ?? [])),
        );

        return redirect()
            ->route('community.roulette.show', $room)
            ->with('roulette_status', 'Sala creada. Las inscripciones del evento han quedado pausadas.');
    }

    public function show(Request $request, CommunityRouletteRoom $room): View
    {
        $this->assertCanView($request);
        $room = $this->roulette->refreshRoomLifecycle($room);
        $room->load([
            'event.activity.activityType',
            'creator',
            'winner',
            'targetSlotType',
            'previousEvents',
            'rules',
            'candidates.currentSlotType',
        ]);
        $this->roulette->heartbeat($room, $request->user());

        $canControl = $this->roulette->canControlRoom($request->user(), $room);

        return view('community.roulette.show', [
            'room' => $room,
            'previousEventOptions' => $this->roulette->previousEventOptions($room->event),
            'canControl' => $canControl,
            'initialState' => $this->roulette->state($room, $request->user()),
            'exceptionalCandidateOptions' => $canControl
                ? $this->roulette->exceptionalCandidateOptions($room)
                : collect(),
        ]);
    }

    public function state(Request $request, CommunityRouletteRoom $room): JsonResponse
    {
        $this->assertCanView($request);

        return response()
            ->json($this->roulette->state($room, $request->user()))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function update(Request $request, CommunityRouletteRoom $room): RedirectResponse
    {
        $this->assertCanView($request);

        $validated = $request->validate([
            'previous_event_ids' => ['nullable', 'array', 'max:3'],
            'previous_event_ids.*' => ['nullable', 'integer', 'distinct', Rule::exists('events', 'id')->whereNull('deleted_at')],
            'responsibility_slot_type_ids' => ['nullable', 'array'],
            'responsibility_slot_type_ids.*' => ['integer', 'distinct', Rule::exists('slot_types', 'id')],
        ]);

        $this->roulette->updateConfiguration(
            $room,
            $request->user(),
            array_values(array_filter($validated['previous_event_ids'] ?? [])),
            $validated['responsibility_slot_type_ids'] ?? [],
        );

        return redirect()
            ->route('community.roulette.show', $room)
            ->with('roulette_status', 'Criterios recalculados. Las papeletas automáticas se han actualizado y los ajustes manuales se han reiniciado.');
    }

    public function addCandidate(Request $request, CommunityRouletteRoom $room): RedirectResponse
    {
        $this->assertCanView($request);

        $validated = $request->validate([
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
        ]);

        $candidateUser = User::query()->findOrFail((int) $validated['user_id']);
        $this->roulette->addExceptionalCandidate(
            $room,
            $request->user(),
            $candidateUser,
        );

        return redirect()
            ->route('community.roulette.show', $room)
            ->with('roulette_status', $candidateUser->nick.' añadido excepcionalmente a la ruleta.');
    }

    public function removeCandidate(
        Request $request,
        CommunityRouletteRoom $room,
        int $user,
    ): RedirectResponse {
        $this->assertCanView($request);
        $this->roulette->removeExceptionalCandidate(
            $room,
            $request->user(),
            $user,
        );

        return redirect()
            ->route('community.roulette.show', $room)
            ->with('roulette_status', 'Participante excepcional eliminado de esta sala.');
    }

    public function adjustCandidateTickets(
        Request $request,
        CommunityRouletteRoom $room,
        int $user,
    ): RedirectResponse {
        $this->assertCanView($request);

        $validated = $request->validate([
            'delta' => ['nullable', 'integer', 'between:-50,50'],
            'reset' => ['nullable', 'boolean'],
        ]);

        $this->roulette->adjustCandidateTickets(
            $room,
            $request->user(),
            $user,
            (int) ($validated['delta'] ?? 0),
            (bool) ($validated['reset'] ?? false),
        );

        return redirect()
            ->route('community.roulette.show', $room)
            ->with('roulette_status', 'Ajuste manual de papeletas aplicado.');
    }

    public function spin(Request $request, CommunityRouletteRoom $room): JsonResponse
    {
        $this->assertCanView($request);
        $room = $this->roulette->startSpin($room, $request->user());

        return response()->json([
            'ok' => true,
            'state' => $this->roulette->state($room, $request->user()),
        ]);
    }

    public function repeat(Request $request, CommunityRouletteRoom $room): RedirectResponse
    {
        $this->assertCanView($request);
        $this->roulette->repeatRoom($room, $request->user());

        return redirect()
            ->route('community.roulette.show', $room)
            ->with('roulette_status', 'Ruleta reabierta. El ganador anterior queda fuera de esta repetición y el ORBAT vuelve a estar bloqueado.');
    }

    public function destroy(Request $request, CommunityRouletteRoom $room): RedirectResponse
    {
        $this->assertCanView($request);
        $this->roulette->closeRoom($room, $request->user());

        return redirect()
            ->route('community.roulette.index')
            ->with('roulette_status', 'La sala se ha cerrado y las inscripciones vuelven a estar disponibles.');
    }

    private function assertCanView(Request $request): void
    {
        abort_unless($this->roulette->canView($request->user()), 403);
    }

    private function assertCanManage(Request $request): void
    {
        abort_unless($this->roulette->canManage($request->user()), 403);
    }
}
