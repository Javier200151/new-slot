<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListEvents extends ListRecords
{
    protected static string $resource = EventResource::class;

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
                ->badge(Event::query()->count()),

            'activo' => Tab::make('Activos')
                ->badge(static::countByStatus('ACTIVO'))
                ->modifyQueryUsing(fn (Builder $query) => static::filterByStatus($query, 'ACTIVO')),

            'finalizado' => Tab::make('Finalizados')
                ->badge(static::countByStatus('FINALIZADO'))
                ->modifyQueryUsing(fn (Builder $query) => static::filterByStatus($query, 'FINALIZADO')),

            'borrador' => Tab::make('Borradores')
                ->badge(static::countByStatus('BORRADOR'))
                ->modifyQueryUsing(fn (Builder $query) => static::filterByStatus($query, 'BORRADOR')),

            'cancelado' => Tab::make('Cancelados')
                ->badge(static::countByStatus('CANCELADO'))
                ->modifyQueryUsing(fn (Builder $query) => static::filterByStatus($query, 'CANCELADO')),
        ];
    }

    protected static function countByStatus(string $status): int
    {
        return Event::query()
            ->whereHas('eventStatus', fn (Builder $query) => $query->where('name', $status))
            ->count();
    }

    protected static function filterByStatus(Builder $query, string $status): Builder
    {
        return $query->whereHas('eventStatus', fn (Builder $query) => $query->where('name', $status));
    }
}
