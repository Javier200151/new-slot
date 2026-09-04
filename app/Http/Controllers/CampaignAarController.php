<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignAar;
use App\Models\Event;
use App\Services\CampaignAarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignAarController extends Controller
{
    public function __construct(
        private readonly CampaignAarService $aarService,
    ) {}

    public function index(Campaign $campaign): View
    {
        $events = Event::query()
            ->whereHas(
                'activity',
                fn ($query) => $query->where('campaign_id', $campaign->id),
            )
            ->whereHas(
                'eventStatus',
                fn ($query) => $query->where('name', 'FINALIZADO'),
            )
            ->with([
                'activity.activityType',
                'activity.editor',
                'eventStatus',
                'campaignAar.commander',
            ])
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $allCampaignEventIds = Event::query()
            ->whereHas(
                'activity',
                fn ($query) => $query->where('campaign_id', $campaign->id),
            )
            ->whereHas(
                'eventStatus',
                fn ($query) => $query->whereIn('name', ['ACTIVO', 'FINALIZADO']),
            )
            ->orderBy('date')
            ->orderBy('id')
            ->pluck('id')
            ->values();

        $events->each(function (Event $event) use ($allCampaignEventIds): void {
            $position = $allCampaignEventIds->search((int) $event->id);
            $event->setAttribute('campaign_sequence', $position === false ? 1 : $position + 1);
        });

        return view('campaigns.aars.index', compact('campaign', 'events'));
    }

    public function show(
        Request $request,
        Campaign $campaign,
        Event $event,
    ): View {
        $event->loadMissing([
            'activity.activityType',
            'activity.editor',
            'activity.platform',
            'activity.map',
            'eventStatus',
        ]);

        abort_unless(
            (int) $event->activity?->campaign_id === (int) $campaign->id,
            404,
        );

        /*
         * Revalida el expediente al abrirlo. Esto corrige AAR pendientes
         * creados por versiones anteriores donde el Mando global no quedó
         * identificado, usando el tipo de slot real del ORBAT.
         */
        $aar = $this->aarService->ensureForFinalizedEvent($event, false);

        abort_unless($aar, 404);

        $aar->load([
            'commander',
            'campaign.editor',
            'event.activity.editor',
            'updatedBy',
        ]);

        $canEdit = $request->user()?->can('update', $aar) ?? false;
        $editing = $canEdit && $request->boolean('editar');
        $sequence = $this->aarService->sequenceNumber($event);

        return view('campaigns.aars.show', compact(
            'campaign',
            'event',
            'aar',
            'canEdit',
            'editing',
            'sequence',
        ));
    }

    public function update(
        Request $request,
        Campaign $campaign,
        Event $event,
    ): RedirectResponse {
        $event->loadMissing('activity');

        abort_unless(
            (int) $event->activity?->campaign_id === (int) $campaign->id,
            404,
        );

        $aar = CampaignAar::query()
            ->where('campaign_id', $campaign->id)
            ->where('event_id', $event->id)
            ->firstOrFail();

        abort_unless(
            $request->user()->can('update', $aar),
            403,
        );

        $validated = $request->validate([
            'sections' => ['nullable', 'array', 'max:20'],
            'sections.*.key' => ['nullable', 'string', 'max:40'],
            'sections.*.title' => ['required', 'string', 'max:120'],
            'sections.*.content' => ['nullable', 'string', 'max:20000'],
            'intent' => ['required', 'in:save,publish'],
        ]);

        $sections = $this->aarService->normalizeSections(
            $validated['sections'] ?? [],
        );

        $isPublishing = $validated['intent'] === 'publish';
        $alreadyPublished = $aar->isPublished();

        $aar->fill([
            'sections' => $sections,
            'updated_by' => $request->user()->id,
            'status' => ($isPublishing || $alreadyPublished) ? 'published' : 'pending',
            'published_at' => $isPublishing
                ? now()
                : $aar->published_at,
        ])->save();

        return redirect()
            ->route('campaigns.aars.show', [$campaign, $event])
            ->with(
                'status',
                $isPublishing
                    ? 'AAR publicado correctamente.'
                    : 'Borrador del AAR guardado.',
            );
    }
}
