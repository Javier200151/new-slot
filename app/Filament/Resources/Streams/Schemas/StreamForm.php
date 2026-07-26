<?php

namespace App\Filament\Resources\Streams\Schemas;

use App\Models\Event;
use App\Models\Streamer;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
                        modifyQueryUsing: fn (Builder $query): Builder => $query->orderByDesc('date'),
                    )
                    ->getOptionLabelFromRecordUsing(fn (Event $event): string => sprintf(
                        '%s · %s',
                        $event->name,
                        $event->date?->format('d/m/Y H:i') ?? 'Sin fecha',
                    ))
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('streamer_id')
                    ->label('Streamer')
                    ->options(fn (): array => Streamer::query()
                        ->with('user')
                        ->orderBy('id')
                        ->get()
                        ->mapWithKeys(fn (Streamer $streamer): array => [
                            $streamer->id => $streamer->user?->nick ?? "Streamer #{$streamer->id}",
                        ])
                        ->all())
                    ->searchable()
                    ->required(),

                TextInput::make('stream_url')
                    ->label('URL de emisión')
                    ->url()
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }
}
