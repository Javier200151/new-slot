<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SquadEasterEggs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Carga multipart convencional: evita el temporal FilePond/Livewire que podía
 * quedarse indefinidamente en «Waiting for size» en este panel.
 */
final class EasterEggZeusMediaController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless((bool) Auth::user()?->hasRole('admin'), 403);

        $validator = Validator::make($request->all(), [
            'zeus_image' => ['nullable', 'file', 'mimes:png', 'max:4096'],
            'zeus_audio' => ['nullable', 'file', 'mimes:mp3,ogg,wav', 'max:4096'],
        ], [
            'zeus_image.mimes' => 'La imagen del ojo debe ser un PNG válido.',
            'zeus_audio.mimes' => 'La nota debe ser un MP3, OGG o WAV válido.',
        ]);
        $validator->after(function ($validator) use ($request): void {
            if (! $request->hasFile('zeus_image') && ! $request->hasFile('zeus_audio')) {
                $validator->errors()->add('zeus_image', 'Selecciona al menos un archivo para subir.');
            }
        });
        $validator->validate();

        $disk = Storage::disk('public');
        $new = [];

        try {
            if ($request->hasFile('zeus_image')) {
                $new['image_path'] = $request->file('zeus_image')->storePublicly('easter-eggs/zeus', 'public');
            }
            if ($request->hasFile('zeus_audio')) {
                $new['audio_path'] = $request->file('zeus_audio')->storePublicly('easter-eggs/zeus', 'public');
            }
            foreach ($new as $path) {
                if (! is_string($path) || $path === '' || ! $disk->exists($path)) {
                    throw new \RuntimeException('No se ha podido guardar el archivo en el disco public.');
                }
            }

            $previous = DB::table('sqa_easter_eggs')->where('key', 'zeus')->first(['image_path', 'audio_path']);
            if (! $previous) {
                throw new \RuntimeException('Falta la configuración del easter egg Zeus. Ejecuta las migraciones.');
            }

            DB::table('sqa_easter_eggs')->where('key', 'zeus')->update([
                ...$new,
                'updated_at' => now(),
            ]);
            SquadEasterEggs::resetCache();

            // No se borran los archivos anteriores: una referencia antigua todavía
            // puede estar abierta en una pestaña o en el caché de un navegador.
        } catch (\Throwable $exception) {
            foreach ($new as $path) {
                if (is_string($path) && $path !== '') {
                    $disk->delete($path);
                }
            }
            report($exception);
            throw ValidationException::withMessages([
                'zeus_image' => 'No se pudo guardar el archivo. Comprueba el directorio storage/app/public y los permisos del servidor.',
            ]);
        }

        return redirect()->to(url('/admin/easter_eggs'))
            ->with('zeus_upload_success', 'Archivos de Zeus guardados correctamente. Ya puedes pulsar Y en la web.');
    }
}
