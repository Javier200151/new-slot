<?php

namespace App\Http\Controllers;

use App\Models\SqaGroup;
use App\Models\Status;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            'status' => [
                'nullable',
                'integer',
                'exists:status,id',
            ],
            'color_by' => [
                'nullable',
                Rule::in(['group', 'status']),
            ],
            'sort' => [
                'nullable',
                Rule::in(['promo', 'alpha']),
            ],
            'direction' => [
                'nullable',
                Rule::in(['asc', 'desc']),
            ],
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $selectedStatusId = filled($validated['status'] ?? null)
            ? (int) $validated['status']
            : null;
        $colorBy = (string) ($validated['color_by'] ?? 'group');
        $sortBy = (string) ($validated['sort'] ?? 'promo');
        $sortDirection = (string) ($validated['direction'] ?? 'asc');

        $sqaGroups = SqaGroup::query()
            ->orderBy('display_order')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'large_name',
                'color',
            ]);

        $statuses = Status::query()
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'color',
            ]);

        $usersQuery = User::query()
            // El directorio público representa a miembros de Squad ALPHA.
            // Cualquier usuario promocionado permanece visible aunque su estado
            // actual sea BAJA, CESADO, RESERVA, etc. Los no promocionados no se listan.
            ->whereNotNull('promo_id')
            ->with([
                'status:id,name,color',
                'promo:id',
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
            ->when(
                $selectedStatusId !== null,
                fn ($query) => $query->where('status_id', $selectedStatusId)
            );

        if ($sortBy === 'alpha') {
            $usersQuery
                ->orderBy('nick', $sortDirection)
                ->orderBy('id', $sortDirection);
        } else {
            // Los usuarios sin promoción permanecen al final incluso al invertir
            // el sentido de las promociones.
            $usersQuery
                ->orderByRaw('CASE WHEN promo_id IS NULL THEN 1 ELSE 0 END')
                ->orderBy('promo_id', $sortDirection)
                ->orderBy('nick');
        }

        // El directorio se muestra completo en una única página.
        // El scroll vertical sustituye a la paginación para poder recorrer
        // todas las promociones o todo el orden alfabético de forma continua.
        $users = $usersQuery->get();

        return view(
            'users.index',
            compact(
                'users',
                'search',
                'sqaGroups',
                'statuses',
                'selectedStatusId',
                'colorBy',
                'sortBy',
                'sortDirection',
            )
        );
    }

    public function show(User $user): View
    {
        $user->load([
            'status:id,name,color',
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
