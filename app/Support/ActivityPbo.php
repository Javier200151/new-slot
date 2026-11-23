<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Platform;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * PBOs are private and kept on the VM's local disk. The basename is never changed;
 * a unique subdirectory avoids collisions between different activities.
 */
class ActivityPbo
{
    public const MAX_SIZE_KB = 204800; // 200 MiB (also adjust PHP / proxy limits).

    public static function appliesTo(mixed $typeId, mixed $platformId): bool
    {
        if (! $typeId || ! $platformId) {
            return false;
        }

        $type = ActivityTypeConfiguration::find($typeId);
        $platform = Platform::query()->find((int) $platformId);

        if (! $type || ! $platform || ! $type->usesPbo() || ! $platform->has_pbo || ! $platform->isArma3()) {
            return false;
        }

        // We do not enable PBOs for training, meetings, or other activity types.
        $typeName = Str::upper(Str::ascii(trim((string) $type->name)));

        return in_array($typeName, ['OPERATIVO', 'OPERATIVOS', 'OPERACION', 'OPERACIONES'], true);
    }

    public static function validFilename(string $name): bool
    {
        // ':' and '?' are intentionally excluded: they cannot be copied to Windows.
        if (strlen($name) > 180 || ! preg_match("/\A[A-Za-z0-9][A-Za-z0-9@._#,'!&$;-]*\.pbo\z/iD", $name)) {
            return false;
        }

        $stem = strtoupper(explode('.', $name)[0]);

        return ! in_array($stem, ['CON', 'PRN', 'AUX', 'NUL'], true)
            && ! preg_match('/\A(?:COM[1-9]|LPT[1-9])\z/', $stem);
    }

    public static function storageFilename(TemporaryUploadedFile $file): string
    {
        $original = $file->getClientOriginalName();

        if (! self::validFilename($original)) {
            throw ValidationException::withMessages([
                'data.pbo_path' => 'Nombre de PBO no válido. Usa un nombre sin espacios ni tildes y terminado en .pbo.',
            ]);
        }

        return (string) Str::uuid() . '/' . $original;
    }

    public static function isOwnStoredPath(string $path): bool
    {
        if (! preg_match('~\Aactivity-pbos/[0-9a-f-]{36}/([^/]+)\z~iD', $path, $match)) {
            return false;
        }

        return self::validFilename($match[1]);
    }

    /**
     * Called before create/update so the metadata and filename are audited
     * along with the activity, not quietly written after it is saved.
     */
    public static function prepare(array $data, ?Activity $activity = null): array
    {
        $eligible = self::appliesTo($data['activity_type_id'] ?? $activity?->activity_type_id, $data['platform_id'] ?? $activity?->platform_id);
        if (! $eligible) {
            // Do not leave an irrelevant PBO attached after changing platform/type.
            return array_merge($data, [
                'pbo_path' => null,
                'pbo_key' => null,
                'pbo' => null,
                'pbo_size_bytes' => null,
                'pbo_uploaded_by' => null,
                'pbo_uploaded_at' => null,
            ]);
        }

        $path = array_key_exists('pbo_path', $data) ? $data['pbo_path'] : $activity?->pbo_path;
        if (filled($path) && (! is_string($path) || ! self::isOwnStoredPath($path) || ! Storage::disk('local')->exists($path))) {
            throw ValidationException::withMessages([
                'data.pbo_path' => 'No se encuentra un PBO válido en el almacenamiento privado. Vuelve a subirlo.',
            ]);
        }

        if ($path === ($activity?->pbo_path ?? null)) {
            // An untouched upload must keep its original uploader and date.
            unset($data['pbo']);
            return $data;
        }

        if (blank($path)) {
            return array_merge($data, [
                'pbo_path' => null,
                'pbo_key' => null,
                'pbo' => null,
                'pbo_size_bytes' => null,
                'pbo_uploaded_by' => null,
                'pbo_uploaded_at' => null,
            ]);
        }

        $name = basename($path);
        $size = Storage::disk('local')->size($path);
        if ($size > self::MAX_SIZE_KB * 1024) {
            throw ValidationException::withMessages(['data.pbo_path' => 'El PBO supera el máximo de 200 MB.']);
        }

        return array_merge($data, [
            'pbo_path' => $path,
            'pbo' => $name,
            'pbo_size_bytes' => $size,
            'pbo_uploaded_by' => auth()->id(),
            'pbo_uploaded_at' => now(),
        ]);
    }

    public static function deletePrevious(?string $previous, ?string $current): void
    {
        \App\Services\PersistentCampaignPbo::cleanupPath($previous, $current);
    }
}
