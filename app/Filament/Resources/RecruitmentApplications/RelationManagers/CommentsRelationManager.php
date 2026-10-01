<?php

namespace App\Filament\Resources\RecruitmentApplications\RelationManagers;

use App\Filament\Forms\BbcodeTextarea;
use App\Support\BbcodeMarkup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'recruitmentComments';

    protected static ?string $title = 'Comentarios de entrevista';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            BbcodeTextarea::make('content')
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
                TextColumn::make('content')
                    ->label('Comentario')
                    ->formatStateUsing(fn ($state): string => BbcodeMarkup::render((string) $state)->toHtml())
                    ->html()
                    ->wrap(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('Añadir comentario')
                    ->mutateDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();

                        return $data;
                    }),
            ])
            ->recordActions([]);
    }
}
