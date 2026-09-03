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

        $operationTypes = ActivityType::query()
            ->whereIn('id', $allowedTypeIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        foreach ($operationTypes as $operationType) {
            $operationTypeId = (int) $operationType->id;

            $tabs['type_' . $operationTypeId] =
                Tab::make($operationType->name)
                    ->badge(
                        (clone $baseQuery)
                            ->where(
                                'operation_type_id',
                                $operationTypeId,
                            )
                            ->count()
                    )
                    ->modifyQueryUsing(
                        fn (Builder $query): Builder =>
                            $query->where(
                                'operation_type_id',
                                $operationTypeId,
                            )
                    );
        }

        return $tabs;
    }
}
