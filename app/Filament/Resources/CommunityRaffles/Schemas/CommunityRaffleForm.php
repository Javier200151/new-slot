<?php

namespace App\Filament\Resources\CommunityRaffles\Schemas;

use App\Models\CommunityRaffle;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\BaseFileUpload;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CommunityRaffleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Información del sorteo')
                ->schema([
                    TextInput::make('title')->label('Título')->required()->maxLength(180)->columnSpanFull(),
                    Textarea::make('description')->label('Descripción pública')->rows(3)->columnSpanFull(),
                    Textarea::make('claim_message')->label('Cómo reclamar los premios')->rows(3)
                        ->helperText('Este mensaje aparece junto a los ganadores cuando se cierra el sorteo.')
                        ->columnSpanFull(),
                    Toggle::make('show_in_sidebar')->label('Mostrar acceso lateral cuando esté abierto o cerrado')
                        ->default(true),
                ]),

            Section::make('Premios')
                ->description('Define el nombre, la fotografía y cuántas unidades se van a sortear. Al abrir el sorteo, los premios quedan fijados; durante la ruleta podrás elegir cuál sortear.')
                ->schema([
                    Repeater::make('prizes')
                        ->relationship('prizes')
                        ->label('Premios disponibles')
                        ->schema([
                            TextInput::make('name')->label('Nombre del premio')->required()->maxLength(180)->columnSpanFull()
                                ->disabled(fn (?CommunityRaffle $record) => $record !== null && $record->status !== 'draft'),
                            Textarea::make('description')->label('Descripción')->rows(2)->columnSpanFull()
                                ->disabled(fn (?CommunityRaffle $record) => $record !== null && $record->status !== 'draft'),
                            FileUpload::make('image_path')->label('Imagen del premio')->image()->disk('public')
                                ->directory('raffle-prizes')
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                ->maxSize(4096)
                                ->getUploadedFileUsing(static function (BaseFileUpload $component, string $file): ?array {
                                    // Servir fotografías existentes desde Laravel evita depender del
                                    // enlace simbólico public/storage en distintos despliegues.
                                    if (! preg_match('~^raffle-prizes/[A-Za-z0-9._-]+$~', $file)) {
                                        return null;
                                    }

                                    $disk = Storage::disk('public');
                                    if (! $disk->exists($file)) {
                                        // Si el fichero ya no existe, Filament no se queda esperando
                                        // indefinidamente su tamaño o previsualización.
                                        return null;
                                    }

                                    return [
                                        'name' => basename($file),
                                        'size' => $disk->size($file),
                                        'type' => $disk->mimeType($file),
                                        'url' => route('raffles.image', ['filename' => basename($file)]),
                                    ];
                                }),
                            TextInput::make('quantity')->label('Unidades')->required()->integer()->minValue(1)->maxValue(999)->default(1)
                                ->disabled(fn (?CommunityRaffle $record) => $record !== null && $record->status !== 'draft'),
                            TextInput::make('sort_order')->label('Orden')->integer()->minValue(0)->default(100)
                                ->disabled(fn (?CommunityRaffle $record) => $record !== null && $record->status !== 'draft'),
                        ])
                        ->columns(2)
                        ->defaultItems(1)
                        ->addActionLabel('Añadir otro premio')
                        ->reorderable(fn (?CommunityRaffle $record) => $record === null || $record->status === 'draft')
                        ->addable(fn (?CommunityRaffle $record) => $record === null || $record->status === 'draft')
                        ->deletable(fn (?CommunityRaffle $record) => $record === null || $record->status === 'draft')
                        ->columnSpanFull(),
                ]),

            Section::make('Mensajes de celebración')
                ->description('Se elige una frase al azar al terminar una tirada. Usa {nick} para el ganador y {premio} para el premio.')
                ->schema([
                    Repeater::make('winner_messages')->label('Frases')
                        ->schema([
                            Textarea::make('text')->label('Mensaje')->rows(2)->required()->maxLength(500),
                        ])
                        ->default([
                            ['text' => 'La suerte ha elegido a {nick}. ¡El premio {premio} tiene nuevo dueño!'],
                            ['text' => 'Después de una investigación absolutamente imparcial, {nick} gana {premio}.'],
                            ['text' => 'La ruleta ha hablado. {nick}, recoge tu {premio} y presume.'],
                        ])
                        ->minItems(1)
                        ->addActionLabel('Añadir frase')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
