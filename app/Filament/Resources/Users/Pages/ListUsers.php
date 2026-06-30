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

            //Usuarios activos miembros hace mas de un año        
            'veteranos1' => Tab::make('1 Año')
                ->badge(User::query()
                    ->whereNotNull('member_at')
                    ->whereDate('member_at', '<=', now()->subYear())
                    ->whereDate('member_at', '>', now()->subYears(3))
                    ->whereHas('status', fn ($query) => $query->where('name', 'ACTIVO'))
                    ->count())
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereNotNull('member_at')
                    ->whereDate('member_at', '<=', now()->subYear())
                    ->whereDate('member_at', '>', now()->subYears(3))
                    ->whereHas('status', fn ($query) => $query->where('name', 'ACTIVO'))),
            //Usuarios activos miembros hace mas de 3 años       
            'veteranos2' => Tab::make('3 Años')
                ->badge(User::query()
                    ->whereNotNull('member_at')
                    ->whereDate('member_at', '<=', now()->subYear(3))
                    ->whereDate('member_at', '>', now()->subYears(5))
                    ->whereHas('status', fn ($query) => $query->where('name', 'ACTIVO'))
                    ->count())
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereNotNull('member_at')
                    ->whereDate('member_at', '<=', now()->subYear(3))
                    ->whereDate('member_at', '>', now()->subYears(5))
                    ->whereHas('status', fn ($query) => $query->where('name', 'ACTIVO'))),    
            //Usuarios activos miembros hace mas de 5 años       
            'veteranos3' => Tab::make('5 Años')
                ->badge(User::query()
                    ->whereNotNull('member_at')
                    ->whereDate('member_at', '<=', now()->subYear(5))
                    ->whereHas('status', fn ($query) => $query->where('name', 'ACTIVO'))
                    ->count())
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereNotNull('member_at')
                    ->whereDate('member_at', '<=', now()->subYear(5))
                    ->whereHas('status', fn ($query) => $query->where('name', 'ACTIVO'))),             
        ];
    }
}
