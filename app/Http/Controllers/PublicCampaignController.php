<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Support\HtmlString;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicCampaignController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'sort' => ['nullable', 'in:published_desc,published_asc,name_asc,name_desc'],
        ]);
        $selectedSort = $filters['sort'] ?? 'published_desc';

        $campaignsQuery = Campaign::query()
            ->withCount([
                'activities as operations_count',
                'events',
            ]);

        match ($selectedSort) {
            'published_asc' => $campaignsQuery->orderBy('id'),
            'name_asc' => $campaignsQuery->orderBy('name')->orderBy('id'),
            'name_desc' => $campaignsQuery->orderByDesc('name')->orderByDesc('id'),
            default => $campaignsQuery->orderByDesc('id'),
        };

        $campaigns = $campaignsQuery->get();

        foreach ($campaigns as $campaign) {
            $campaign->setAttribute(
                'summary',
                trim(strip_tags(
                    RichContentRenderer::make($campaign->description)->toHtml()
                )),
            );
        }

        return view('campaigns.index', compact('campaigns', 'selectedSort'));
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
                    'campaignAar:id,event_id,status',
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

        $description = new HtmlString(
            RichContentRenderer::make($campaign->description)->toHtml(),
        );

        $finalizedCampaignEvents = $campaign->events
            ->filter(
                fn ($event): bool =>
                    mb_strtoupper(trim((string) $event->eventStatus?->name)) === 'FINALIZADO'
            );

        $campaignAarPublishedCount = $finalizedCampaignEvents
            ->filter(fn ($event): bool => $event->campaignAar?->status === 'published')
            ->count();

        $campaignAarPendingCount = $finalizedCampaignEvents->count()
            - $campaignAarPublishedCount;

        $campaignEventIdsAscending = $campaign->events
            ->sortBy(fn ($event) => $event->date?->getTimestamp() ?? PHP_INT_MAX)
            ->pluck('id')
            ->values();

        $campaignAarPendingEvent = $finalizedCampaignEvents
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
