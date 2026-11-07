<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Support\BbcodeMarkup;
use Illuminate\Support\HtmlString;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicCampaignController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'persistent' => ['nullable', 'in:all,yes,no'],
            'events' => ['nullable', 'in:all,with,without'],
            'sort' => ['nullable', 'in:published_desc,published_asc,name_asc,name_desc,first_event_asc,first_event_desc'],
        ]);

        $selectedSearch = trim((string) ($filters['q'] ?? ''));
        $selectedPersistent = $filters['persistent'] ?? 'all';
        $selectedEvents = $filters['events'] ?? 'all';
        $selectedSort = $filters['sort'] ?? 'published_desc';

        $campaignsQuery = Campaign::query()
            ->withCount([
                'activities as operations_count',
                'events',
            ])
            // Primera partida visible de la campaña; no usamos borradores
            // ni cancelados para ordenar o mostrar fechas en el listado público.
            ->withMin([
                'events as first_public_event_date' => fn ($query) => $query
                    ->whereHas('eventStatus', fn ($query) => $query
                        ->whereIn('name', ['ACTIVO', 'FINALIZADO'])),
            ], 'date');

        if ($selectedSearch !== '') {
            $campaignsQuery->where('name', 'like', '%' . $selectedSearch . '%');
        }

        if ($selectedPersistent !== 'all') {
            $campaignsQuery->where('persistent', $selectedPersistent === 'yes');
        }

        if ($selectedEvents === 'with') {
            $campaignsQuery->has('events');
        } elseif ($selectedEvents === 'without') {
            $campaignsQuery->doesntHave('events');
        }

        match ($selectedSort) {
            'published_asc' => $campaignsQuery->orderBy('id'),
            'name_asc' => $campaignsQuery->orderBy('name')->orderBy('id'),
            'name_desc' => $campaignsQuery->orderByDesc('name')->orderByDesc('id'),
            'first_event_asc' => $campaignsQuery
                ->orderByRaw('first_public_event_date IS NULL')
                ->orderBy('first_public_event_date')
                ->orderBy('name')
                ->orderBy('id'),
            'first_event_desc' => $campaignsQuery
                ->orderByRaw('first_public_event_date IS NULL')
                ->orderByDesc('first_public_event_date')
                ->orderBy('name')
                ->orderBy('id'),
            default => $campaignsQuery->orderByDesc('id'),
        };

        $campaigns = $campaignsQuery->get();

        foreach ($campaigns as $campaign) {
            $campaign->setAttribute(
                'summary',
                trim(strip_tags((string) BbcodeMarkup::render($campaign->description))),
            );

            $campaign->setAttribute(
                'first_event_date_label',
                $campaign->first_public_event_date
                    ? \Illuminate\Support\Carbon::parse($campaign->first_public_event_date)->format('d/m/Y')
                    : null,
            );
        }

        return view('campaigns.index', compact(
            'campaigns',
            'selectedSort',
            'selectedSearch',
            'selectedPersistent',
            'selectedEvents',
        ));
    }

    public function show(Campaign $campaign): View
    {
        $campaign->load([
            'events' => fn ($query) => $query
                ->whereHas('eventStatus', fn ($query) => $query
                    ->whereIn('name', ['ACTIVO', 'FINALIZADO']))
                ->with([
                    'eventStatus',
                    'eventResult',
                    'activity.activityType',
                    'activity.campaign',
                    'activity.period',
                    'activity.platform',
                    'activity.map',
                    'campaignAar:id,event_id,status,is_visible',
                    'slots:id,event_id,slot_key,user_id,ally_id',
                ])
                ->withCount([
                    'slots as occupied_slots_count' => fn ($query) => $query
                        ->where(fn ($query) => $query
                            ->whereNotNull('user_id')
                            ->orWhereNotNull('ally_id')),
                ])
                ->orderByDesc('date'),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Ocupación real de slots visibles
        |--------------------------------------------------------------------------
        |
        | Las tarjetas de eventos deben contar únicamente asignaciones que
        | pertenezcan a slots visibles dentro de grupos visibles.
        |
        */

        foreach ($campaign->events as $event) {

            $visibleSlotKeys = collect(
                $event->orbat['groups'] ?? []
            )
                ->filter(
                    fn (array $group): bool =>
                        (bool) (
                            $group['visible']
                            ?? true
                        )
                )
                ->flatMap(
                    fn (array $group) =>
                        collect(
                            $group['slots']
                            ?? []
                        )
                            ->filter(
                                fn (array $slot): bool =>
                                    (bool) (
                                        $slot['visible']
                                        ?? true
                                    )
                            )
                            ->pluck('slot_key')
                )
                ->filter()
                ->map(
                    fn ($slotKey): string =>
                        (string) $slotKey
                )
                ->unique()
                ->values();

            $visibleOccupiedSlotsCount =
                $event->slots
                    ->filter(
                        fn ($slot): bool =>
                            $visibleSlotKeys->contains(
                                (string) $slot->slot_key
                            )
                            && (
                                $slot->user_id !== null
                                || $slot->ally_id !== null
                            )
                    )
                    ->pluck('slot_key')
                    ->map(
                        fn ($slotKey): string =>
                            (string) $slotKey
                    )
                    ->unique()
                    ->count();

            $event->setAttribute(
                'visible_occupied_slots_count',
                $visibleOccupiedSlotsCount
            );
        }

        $campaignFirstEvent = $campaign->events
            ->sortBy(fn ($event) => $event->date?->getTimestamp() ?? PHP_INT_MAX)
            ->first();

        $campaignCoverImage = $campaignFirstEvent?->activity?->image;

        $description = BbcodeMarkup::render($campaign->description);

        $finalizedCampaignEvents = $campaign->events
            ->filter(
                fn ($event): bool =>
                    mb_strtoupper(trim((string) $event->eventStatus?->name)) === 'FINALIZADO'
            );

        $publicFinalizedCampaignEvents = $finalizedCampaignEvents
            ->filter(
                fn ($event): bool =>
                    $event->campaignAar === null
                    || $event->campaignAar->is_visible
            );

        $campaignAarPublishedCount = $publicFinalizedCampaignEvents
            ->filter(fn ($event): bool => $event->campaignAar?->status === 'published')
            ->count();

        $campaignAarPendingCount = $publicFinalizedCampaignEvents->count()
            - $campaignAarPublishedCount;

        $campaignEventIdsAscending = $campaign->events
            ->sortBy(fn ($event) => $event->date?->getTimestamp() ?? PHP_INT_MAX)
            ->pluck('id')
            ->values();

        $campaignAarPendingEvent = $publicFinalizedCampaignEvents
            ->filter(fn ($event): bool => $event->campaignAar?->status !== 'published')
            ->sortByDesc(fn ($event) => $event->date?->getTimestamp() ?? 0)
            ->first();

        if ($campaignAarPendingEvent) {
            $position = $campaignEventIdsAscending->search((int) $campaignAarPendingEvent->id);
            $campaignAarPendingEvent->setAttribute(
                'campaign_sequence',
                $position === false ? 1 : $position + 1,
            );
        }

        return view('campaigns.show', compact(
            'campaign',
            'description',
            'campaignFirstEvent',
            'campaignCoverImage',
            'campaignAarPublishedCount',
            'campaignAarPendingCount',
            'campaignAarPendingEvent',
        ));
    }
}
