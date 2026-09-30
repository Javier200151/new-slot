<?php

namespace App\Filament\Resources\RecruitmentPeriods\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';
    protected static ?string $title = 'Comentarios internos';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('content')
                ->label('Comentario')
                ->required()
                ->rows(4)
                ->maxLength(5000),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('author.nick')->label('Autor')->default('Sistema'),
                TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('content')->label('Comentario')->wrap(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('Añadir comentario')
                    ->visible(fn (): bool => $this->getOwnerRecord()->isOpen())
                    ->mutateDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();
                        return $data;
                    }),
            ])
            ->recordActions([]);
    }
}
