<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nick')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('promo_id')
                    ->label('Promoción')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('tagname')
                    ->searchable(),
                TextColumn::make('status.name')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ACTIVO' => 'success',
                        'BAJA' => 'warning',
                        'CESADO' => 'warning',
                        'RECLUTA' => 'info',
                        'RESERVA' => 'info',
                        'USUARIO' => 'info',
                        default => 'gray',
                    })
                    ->sortable()
                    ->searchable(),
                TextColumn::make('roles.name')
                    ->label('Rol')
                    ->badge()
                    ->separator(',')
                    ->searchable(),    
                TextColumn::make('firma')
                    ->searchable(),
                TextColumn::make('arma_uid')
                    ->searchable(),
                TextColumn::make('discord_id')
                    ->searchable(),
                TextColumn::make('steam_id')
                    ->searchable(),
                TextColumn::make('member_at')
                    ->date()
                    ->sortable(),
                //TextColumn::make('created_at')
                //    ->dateTime()
                //    ->sortable()
                //    ->toggleable(isToggledHiddenByDefault: true),
                //TextColumn::make('updated_at')
                //    ->dateTime()
                //    ->sortable()
                //    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('birth_at')
                    ->label('Nacimiento')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('tutor.nick')
                    ->label('Tutor')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('createdby.nick')
                    ->label('Creado por')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updatedBy.nick')
                    ->label('Actualizado por')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            //Filtros en listado
            ->filters([
                Filter::make('Miembros activos')
                    ->query(fn (Builder $query) => $query->whereHas('status', fn ($query) => $query->where('name', 'ACTIVO'))),
                
                //Busca las opciones disponibles y las muestra para selección
                SelectFilter::make('Estado')
                    ->multiple()                 
                    ->attribute('status_id') 
                    ->relationship('status', 'name'),       
                SelectFilter::make('Rol')
                    ->multiple()                 
                    ->attribute('roles_id') 
                    ->relationship('roles', 'name'),           
                    
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
