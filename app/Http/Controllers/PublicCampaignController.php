<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;

class PublicCampaignController extends Controller
{
    public function show(Campaign $campaign): View
    {
        $campaign->load([
            'events' => fn ($query) => $query
                ->whereHas('eventStatus', fn ($query) => $query
                    ->whereIn('name', ['ACTIVO', 'FINALIZADO']))
                ->with([
                    'eventStatus',
                    'eventResult',
                    'operation.operationType',
                    'operation.campaign',
                    'operation.period',
                    'operation.platform',
                    'operation.map',
                ])
                ->withCount([
                    'slots as occupied_slots_count' => fn ($query) => $query
                        ->where(fn ($query) => $query
                            ->whereNotNull('user_id')
                            ->orWhereNotNull('ally_id')),
                ])
                ->orderByDesc('date'),
        ]);

        $description = new HtmlString(
            RichContentRenderer::make($campaign->description)->toHtml(),
        );

        return view('campaigns.show', compact('campaign', 'description'));
    }
}
