<?php

namespace App\Http\Controllers;

use App\Models\SqaGroup;
use Illuminate\View\View;

class CommunityController extends Controller
{
    public function organization(): View
    {
        $groups = SqaGroup::query()
            ->where('show_in_organization', true)
            ->with([
                'users' => fn ($query) => $query
                    ->with(['status', 'mainSqaGroup'])
                    ->orderBy('nick'),
            ])
            ->orderByRaw('display_order IS NULL')
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return view('community.organization', compact('groups'));
    }
}
