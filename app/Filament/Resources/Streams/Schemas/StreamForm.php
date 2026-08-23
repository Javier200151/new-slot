<?php

namespace App\Filament\Resources\Streams\Schemas;

use App\Models\Event;
use App\Models\Streamer;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class StreamForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('event_id')
                    ->label('Evento')
                    ->relationship(
                        'event',
                        'name',
                        modifyQueryUsing:
                            fn (Builder $query): Builder =>
                                $query->orderByDesc('date'),
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (Event $event): string =>
                            sprintf(
                                '%s · %s',
                                $event->name,
                                $event->date?->format(
                                    'd/m/Y H:i'
                                ) ?? 'Sin fecha',
                            )
                    )
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('streamer_id')
                    ->label('Streamer')
                    ->options(
                        fn (): array =>
                            Streamer::query()
                                ->with('user')
                                ->where('enable', true)
                                ->get()
                                ->mapWithKeys(
                                    fn (
                                        Streamer $streamer
                                    ): array => [
                                        $streamer->id =>
                                            $streamer
                                                ->user
                                                ?->nick
                                            ?? "Streamer #{$streamer->id}",
                                    ]
                                )
                                ->all()
                    )
                    ->searchable()
                    ->required(),

                Select::make('platform')
                    ->label('Plataforma')
                    ->options([
                        'twitch' => 'Twitch',
                        'youtube' => 'YouTube',
                    ])
                    ->required(),

                TextInput::make('title')
                    ->label('Título')
                    ->maxLength(255)
                    ->nullable(),

                TextInput::make('stream_url')
                    ->label('URL de emisión')
                    ->url()
                    ->required()
                    ->maxLength(500)
                    ->columnSpanFull(),

                Toggle::make('enabled')
                    ->label('Emisión visible')
                    ->default(false),

                DateTimePicker::make('started_at')
                    ->label('Inicio')
                    ->seconds(false)
                    ->nullable(),

                DateTimePicker::make('ended_at')
                    ->label('Fin')
                    ->seconds(false)
                    ->nullable(),
            ]);
    }
}