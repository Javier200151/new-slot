<?php

namespace App\Filament\Resources\Operations\Pages;

use App\Models\Operation;
use App\Filament\Resources\Operations\OperationResource;
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
        return [
            'todos' => Tab::make('Todos')
                ->badge(Operation::query()->count()),

            'operacion' => Tab::make('Operaciones')
                ->badge(static::countByType('OPERACIÓN'))
                ->modifyQueryUsing(fn (Builder $query) => static::filterByType($query, 'OPERACIÓN')),

            'curso' => Tab::make('Cursos')
                ->badge(static::countByType('CURSO'))
                ->modifyQueryUsing(fn (Builder $query) => static::filterByType($query, 'CURSO')),

            'maniobras' => Tab::make('Maniobras')
                ->badge(static::countByType('MANIOBRAS'))
                ->modifyQueryUsing(fn (Builder $query) => static::filterByType($query, 'MANIOBRAS')),

            'practicas' => Tab::make('Practicas')
                ->badge(static::countByType('PRACTICAS'))
                ->modifyQueryUsing(fn (Builder $query) => static::filterByType($query, 'PRACTICAS')),

            'reunion' => Tab::make('Reuniones')
                ->badge(static::countByType('REUNIÓN'))
                ->modifyQueryUsing(fn (Builder $query) => static::filterByType($query, 'REUNIÓN')),
        ];
    }

    protected static function countByType(string $type): int
    {
        return Operation::query()
            ->whereHas('operationType', fn (Builder $query) => $query->where('name', $type))
            ->count();
    }

    protected static function filterByType(Builder $query, string $type): Builder
    {
        return $query->whereHas('operationType', fn (Builder $query) => $query->where('name', $type));
    }
}
