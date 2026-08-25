<?php

namespace App\Filament\Resources\Streamers\Pages;

use App\Filament\Resources\Streamers\StreamerResource;
use App\Models\Streamer;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStreamer extends CreateRecord
{
    protected static string $resource = StreamerResource::class;

    protected function handleRecordCreation(
        array $data
    ): Model {
        /*
        |--------------------------------------------------------------------------
        | Buscar streamer existente, incluyendo eliminados
        |--------------------------------------------------------------------------
        |
        | Como user_id es UNIQUE en la base de datos, un streamer eliminado
        | mediante SoftDeletes sigue ocupando ese user_id.
        |
        | Si existe eliminado, lo restauramos en lugar de crear uno nuevo.
        |
        */

        $streamer = Streamer::withTrashed()
            ->where('user_id', $data['user_id'])
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Restaurar streamer eliminado
        |--------------------------------------------------------------------------
        */

        if ($streamer?->trashed()) {
            $streamer->restore();

            $streamer->fill($data);

            $streamer->save();

            return $streamer;
        }

        /*
        |--------------------------------------------------------------------------
        | Crear streamer nuevo
        |--------------------------------------------------------------------------
        */

        return Streamer::create($data);
    }
}