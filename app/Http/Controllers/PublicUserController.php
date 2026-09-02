<?php

namespace App\Http\Controllers;

use App\Models\SqaGroup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicUserController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => [
                'nullable',
                'string',
                'max:50',
            ],
        ]);

        $search = trim(
            (string) ($validated['q'] ?? '')
        );

        $sqaGroups = SqaGroup::query()
            ->orderBy('display_order')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'large_name',
                'color',
            ]);

        $users = User::query()
            ->with([
                'status:id,name',
                'mainSqaGroup',
            ])
            ->when(
                $search !== '',
                fn ($query) => $query->where(
                    'nick',
                    'like',
                    '%' . $search . '%'
                )
            )
            ->orderBy('nick')
            ->paginate(24)
            ->withQueryString();

        return view(
            'users.index',
            compact(
                'users',
                'search',
                'sqaGroups',
            )
        );
    }

    public function show(User $user): View
    {
        $user->load([
            'status:id,name',
            'promo',
            'sqaGroups',
            'mainSqaGroup',
            'metopas.sqaGroup',
        ]);

        return view(
            'users.show',
            compact('user')
        );
    }
}