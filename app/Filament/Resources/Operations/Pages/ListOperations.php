<?php

namespace App\Filament\Resources\Operations\Pages;

use App\Filament\Resources\Operations\OperationResource;
use App\Models\OperationType;
use App\Support\OperationTypeAccess;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOperations extends ListRecords
{
    protected static string $resource = OperationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $baseQuery = OperationResource::getEloquentQuery();

        $tabs = [
            'todos' => Tab::make('Todos')
                ->badge((clone $baseQuery)->count()),
        ];

        $allowedTypeIds = OperationTypeAccess::allowedTypeIds(
            auth()->user(),
            'operations',
            'view',
        );

        $operationTypes = OperationType::query()
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
