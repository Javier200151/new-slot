<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

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
                ->badge(User::query()->count()),
            'activos' => Tab::make('Activos')
                ->badge(User::query()->whereHas('status', fn (Builder $query) => $query->where('name', 'ACTIVO'))->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('status', fn ($query) => $query->where('name', 'ACTIVO'))),

            'reclutas' => Tab::make('Reclutas')
                ->badge(User::query()->whereHas('status', fn (Builder $query) => $query->where('name', 'RECLUTA'))->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('status', fn ($query) => $query->where('name', 'RECLUTA'))),

            'reserva' => Tab::make('Reserva')
                ->badge(User::query()->whereHas('status', fn (Builder $query) => $query->where('name', 'RESERVA'))->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('status', fn ($query) => $query->where('name', 'RESERVA'))),
        ];
    }
}
