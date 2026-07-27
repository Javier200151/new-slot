<?php

namespace App\Filament\Resources\Metopas\RelationManagers;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $relatedResource = UserResource::class;

    protected static ?string $title = 'Miembros';

    /**
     * Permite visualizar la relación a cualquier usuario
     * que pueda acceder a la metopa.
     */
    public static function canViewForRecord(
        Model $ownerRecord,
        string $pageClass
    ): bool {
        return true;
    }

    /**
     * Este Relation Manager es exclusivamente de consulta.
     */
    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nick')
                    ->label('Nick')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('pivot.assigned_at')
                    ->label('Fecha de asignación')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(
                        query: fn (
                            Builder $query,
                            string $direction
                        ): Builder => $query->orderBy(
                            'metopa_user.assigned_at',
                            $direction
                        )
                    ),
            ])
            ->defaultSort(
                fn (Builder $query): Builder =>
                    $query->orderBy(
                        'metopa_user.assigned_at',
                        'asc'
                    )
            )
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}