<?php

namespace App\Filament\Resources\Activities\Pages;

use App\Filament\Resources\Activities\ActivityResource;
use App\Models\ActivityType;
use App\Support\ActivityTypeAccess;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListActivities extends ListRecords
{
    protected static string $resource = ActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $baseQuery = ActivityResource::getEloquentQuery();

        $tabs = [
            'todos' => Tab::make('Todos')
                ->badge((clone $baseQuery)->count()),
        ];

        $allowedTypeIds = ActivityTypeAccess::allowedTypeIds(
            auth()->user(),
            'activities',
            'view',
        );

        $activityTypes = ActivityType::query()
            ->whereIn('id', $allowedTypeIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        foreach ($activityTypes as $activityType) {
            $activityTypeId = (int) $activityType->id;

            $tabs['type_' . $activityTypeId] =
                Tab::make($activityType->name)
                    ->badge(
                        (clone $baseQuery)
                            ->where(
                                'activity_type_id',
                                $activityTypeId,
                            )
                            ->count()
                    )
                    ->modifyQueryUsing(
                        fn (Builder $query): Builder =>
                            $query->where(
                                'activity_type_id',
                                $activityTypeId,
                            )
                    );
        }

        return $tabs;
    }
}
