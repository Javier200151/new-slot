<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventMedia extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Tabla
    |--------------------------------------------------------------------------
    */

    protected $table = 'event_media';


    /*
    |--------------------------------------------------------------------------
    | Tipos
    |--------------------------------------------------------------------------
    */

    public const TYPE_CLIP = 'clip';

    public const TYPE_VOD = 'vod';


    /*
    |--------------------------------------------------------------------------
    | Proveedores
    |--------------------------------------------------------------------------
    */

    public const PROVIDER_YOUTUBE = 'youtube';

    public const PROVIDER_TWITCH = 'twitch';


    /*
    |--------------------------------------------------------------------------
    | Campos asignables
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'event_id',
        'user_id',
        'type',
        'provider',
        'url',
        'external_id',
        'title',
    ];


    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    public function event()
    {
        return $this->belongsTo(
            Event::class
        );
    }


    public function user()
    {
        return $this->belongsTo(
            User::class
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Helpers de tipo
    |--------------------------------------------------------------------------
    */

    public function isClip(): bool
    {
        return $this->type
            === self::TYPE_CLIP;
    }


    public function isVod(): bool
    {
        return $this->type
            === self::TYPE_VOD;
    }


    /*
    |--------------------------------------------------------------------------
    | Helpers de proveedor
    |--------------------------------------------------------------------------
    */

    public function isYoutube(): bool
    {
        return $this->provider
            === self::PROVIDER_YOUTUBE;
    }


    public function isTwitch(): bool
    {
        return $this->provider
            === self::PROVIDER_TWITCH;
    }


    /*
    |--------------------------------------------------------------------------
    | Nombre del proveedor
    |--------------------------------------------------------------------------
    */

    public function getProviderName(): string
    {
        return match ($this->provider) {

            self::PROVIDER_YOUTUBE =>
                'YouTube',

            self::PROVIDER_TWITCH =>
                'Twitch',

            default =>
                ucfirst(
                    (string) $this->provider
                ),
        };
    }


    /*
    |--------------------------------------------------------------------------
    | URL embebida
    |--------------------------------------------------------------------------
    |
    | external_id ya vendrá normalizado cuando creemos
    | el medio desde el controlador.
    |
    | Ejemplos:
    |
    | YouTube:
    |     dQw4w9WgXcQ
    |
    | Twitch clip:
    |     AwkwardHelplessSalamander...
    |
    | Twitch VOD:
    |     1234567890
    |
    */

    public function getEmbedUrl(
        ?string $parent = null
    ): ?string {

        if (blank($this->external_id)) {
            return null;
        }


        /*
        |--------------------------------------------------------------------------
        | YouTube
        |--------------------------------------------------------------------------
        */

        if ($this->isYoutube()) {

            return 'https://www.youtube.com/embed/'
                . rawurlencode(
                    $this->external_id
                );
        }


        /*
        |--------------------------------------------------------------------------
        | Twitch
        |--------------------------------------------------------------------------
        */

        if ($this->isTwitch()) {

            /*
             * Twitch obliga a indicar el dominio
             * que está mostrando el reproductor.
             */

            $parent ??=
                request()->getHost();

            if ($this->isClip()) {

                return 'https://clips.twitch.tv/embed'
                    . '?clip='
                    . rawurlencode(
                        $this->external_id
                    )
                    . '&parent='
                    . rawurlencode(
                        $parent
                    );
            }


            if ($this->isVod()) {

                return 'https://player.twitch.tv/'
                    . '?video='
                    . rawurlencode(
                        $this->external_id
                    )
                    . '&parent='
                    . rawurlencode(
                        $parent
                    )
                    . '&autoplay=false';
            }
        }


        return null;
    }


    /*
    |--------------------------------------------------------------------------
    | ¿Se puede incrustar?
    |--------------------------------------------------------------------------
    */

    public function canEmbed(): bool
    {
        return filled(
            $this->getEmbedUrl()
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Título mostrado
    |--------------------------------------------------------------------------
    */

    public function getDisplayTitle(): string
    {
        if (filled($this->title)) {
            return $this->title;
        }

        if ($this->isClip()) {
            return 'Clip';
        }

        return 'Partida completa';
    }


    /*
    |--------------------------------------------------------------------------
    | Usuario que lo añadió
    |--------------------------------------------------------------------------
    */

    public function getAddedByName(): string
    {
        return $this->user?->nick
            ?? 'Usuario eliminado';
    }
}