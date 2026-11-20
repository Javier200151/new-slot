<?php

namespace App\Http\Controllers;

use App\Models\ActivityType;
use App\Models\Campaign;
use App\Models\GameMap;
use App\Models\Platform;
use App\Models\SlotType;
use App\Models\StatisticsSetting;
use App\Models\Status;
use App\Models\User;
use App\Services\PublicStatisticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicStatisticsController extends Controller
{
    public function index(Request $request, PublicStatisticsService $statistics): View
    {
        abort_unless(StatisticsSetting::current()->canView($request->user()), 403);

        $data = $request->validate([
            'focus' => ['nullable', 'in:role,activity_type,editor,user'],
            'metric' => ['nullable', 'in:participations,events,missions'],
            'group' => ['nullable', 'in:user,role,month,editor,platform,activity_type,map,campaign'],
            'period' => ['nullable', 'in:1m,3m,6m,12m,year,all,custom'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'platform' => ['nullable', 'integer', 'min:1', 'exists:platforms,id'],
            'activity_type' => ['nullable', 'integer', 'min:1', 'exists:activity_types,id'],
            'map' => ['nullable', 'integer', 'min:1', 'exists:maps,id'],
            'campaign' => ['nullable', 'integer', 'min:1', 'exists:campaign,id'],
            'role' => ['nullable', 'integer', 'min:1', 'exists:slot_types,id'],
            'user' => ['nullable', 'integer', 'min:1', 'exists:users,id'],
            'editor' => ['nullable', 'integer', 'min:1', 'exists:users,id'],
            'status' => ['nullable', 'integer', 'min:1', 'exists:status,id'],
            'top' => ['nullable', 'integer', 'in:5,10,20,50,100'],
            'sort' => ['nullable', 'in:asc,desc'],
        ]);
        $filters = $statistics->normalized($data);
        // No usar nombres de usuarios eliminados o sin perfil público como filtros.
        foreach (['user', 'editor'] as $field) {
            if ($filters[$field] && ! User::query()->whereNotNull('promo_id')->whereKey($filters[$field])->exists()) {
                $filters[$field] = 0;
            }
        }

        return view('statistics.index', [
            'filters' => $filters,
            'rows' => $statistics->ranking($filters),
            'metricOptions' => PublicStatisticsService::METRICS,
            'groupOptions' => PublicStatisticsService::GROUPS,
            'periodOptions' => PublicStatisticsService::PERIODS,
            'platforms' => Platform::query()->orderBy('name')->pluck('name', 'id'),
            'activityTypes' => ActivityType::query()->orderBy('name')->pluck('name', 'id'),
            'maps' => GameMap::query()->orderBy('name')->pluck('name', 'id'),
            'campaigns' => Campaign::query()->orderBy('name')->pluck('name', 'id'),
            'roles' => SlotType::query()->orderBy('name')->pluck('name', 'id'),
            'members' => User::query()->whereNotNull('promo_id')->orderBy('nick')->pluck('nick', 'id'),
            'statuses' => Status::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
