<?php

namespace App\Filament\Resources\CommunityRaffles\Schemas;

use App\Models\CommunityRaffle;
use App\Models\CommunityRafflePrize;
use Illuminate\Database\Eloquent\Model;
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
                ->description('Puedes corregir el nombre, la descripción y la imagen mientras el sorteo esté abierto. Las unidades, el orden y la lista de premios se fijan al abrirlo.')
                ->schema([
                    Repeater::make('prizes')
                        ->relationship('prizes')
                        ->label('Premios disponibles')
                        ->schema([
                            TextInput::make('name')->label('Nombre del premio')->required()->maxLength(180)->columnSpanFull()
                                ->disabled(fn (?Model $record): bool => ! self::canEditPrizeDetails($record)),
                            Textarea::make('description')->label('Descripción')->rows(2)->columnSpanFull()
                                ->disabled(fn (?Model $record): bool => ! self::canEditPrizeDetails($record)),
                            FileUpload::make('image_path')->label('Imagen del premio')->image()->disk('public')
                                ->directory('raffle-prizes')
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                ->maxSize(4096)
                                ->disabled(fn (?Model $record): bool => ! self::canEditPrizeDetails($record))
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
                                ->disabled(fn (?Model $record): bool => ! self::canEditPrizes($record)),
                            TextInput::make('sort_order')->label('Orden')->integer()->minValue(0)->default(100)
                                ->disabled(fn (?Model $record): bool => ! self::canEditPrizes($record)),
                        ])
                        ->columns(2)
                        ->defaultItems(1)
                        ->addActionLabel('Añadir otro premio')
                        ->reorderable(fn (?Model $record): bool => self::canEditPrizes($record))
                        ->addable(fn (?Model $record): bool => self::canEditPrizes($record))
                        ->deletable(fn (?Model $record): bool => self::canEditPrizes($record))
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

    /**
     * Filament evaluates callbacks inside a relationship Repeater with the
     * current prize record, not necessarily the parent raffle. Using a
     * CommunityRaffle-only type causes a TypeError on the edit screen.
     *
     * A new form has no record; existing prizes inherit their parent status.
     */
    /**
     * Names, descriptions and images are presentation details and can be
     * corrected after opening. Prize inventory and drawing structure cannot.
     * A closed raffle retains its historical presentation unchanged.
     */
    public static function canEditPrizeDetails(?Model $record): bool
    {
        if ($record === null) {
            return true;
        }

        if ($record instanceof CommunityRaffle) {
            return in_array($record->status, ['draft', 'active'], true);
        }

        if ($record instanceof CommunityRafflePrize) {
            return in_array($record->raffle?->status, ['draft', 'active'], true);
        }

        return false;
    }

    public static function canEditPrizes(?Model $record): bool
    {
        if ($record === null) {
            return true;
        }

        if ($record instanceof CommunityRaffle) {
            return $record->status === 'draft';
        }

        if ($record instanceof CommunityRafflePrize) {
            return $record->raffle?->status === 'draft';
        }

        return false;
    }
}
