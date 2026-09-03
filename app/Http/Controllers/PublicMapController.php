<?php

namespace App\Http\Controllers;

use App\Models\GameMap;
use Illuminate\View\View;

class PublicMapController extends Controller
{
    public function show(GameMap $map): View
    {
        $map->load([
            'platform',
            'activities' => fn ($query) => $query
                ->with([
                    'activityType',
                    'activityStatus',
                    'platform',
                    'period',
                    'editor.status',
                    'editor.mainSqaGroup',
                ])
                ->orderBy('name'),
        ]);

        return view('maps.show', compact('map'));
    }
}
