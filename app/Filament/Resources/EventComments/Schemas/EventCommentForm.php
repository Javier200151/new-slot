<?php

namespace App\Filament\Resources\EventComments\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class EventCommentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('event_id')
                    ->label('Evento')
                    ->relationship('event', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('user_id')
                    ->label('Usuario')
                    ->relationship('user', 'nick')
                    ->searchable()
                    ->preload()
                    ->nullable(),

                Select::make('parent_id')
                    ->label('Respuesta a')
                    ->relationship('parent', 'comment')
                    ->searchable()
                    ->preload()
                    ->nullable(),

                Textarea::make('comment')
                    ->label('Comentario')
                    ->required()
                    ->rows(5)
                    ->columnSpanFull(),

                Toggle::make('is_pinned')
                    ->label('Fijado')
                    ->inline(false)
                    ->default(false),
            ]);
    }
}
