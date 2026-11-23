<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityPboExport;
use App\Models\Campaign;
use App\Support\ActivityPbo;
use App\Support\ActivityTypeAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** The PBO is shared by campaign only when campaign.persistent is true. */
final class PersistentCampaignPbo
{
    public static function campaignKey(?int $campaignId): ?string
    {
        return $campaignId && Campaign::query()->whereKey($campaignId)->where('persistent', true)->exists()
            ? 'campaign:' . $campaignId : null;
    }

    public static function key(Activity $activity): string
    {
        return self::campaignKey($activity->campaign_id) ?: 'activity:' . $activity->getKey();
    }

    public static function source(?int $campaignId, ?int $exceptId = null): ?Activity
    {
        if (! self::campaignKey($campaignId)) { return null; }
        return Activity::withTrashed()->where('campaign_id', $campaignId)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->whereNotNull('pbo_path')
            ->orderByRaw('deleted_at IS NOT NULL')
            ->orderByDesc('pbo_uploaded_at')->orderByDesc('id')->first();
    }

    /** Called before the PBO's metadata is prepared and before the form is saved. */
    public function inherit(array $data, ?Activity $original): array
    {
        $campaignId = isset($data['campaign_id']) ? (int) $data['campaign_id'] : $original?->campaign_id;
        $group = self::campaignKey($campaignId);
        if (! $group) {
            // Leaving a persistent campaign must never keep its shared file by reference.
            if ($original && str_starts_with((string) $original->pbo_key, 'campaign:')
                && ($data['pbo_path'] ?? $original->pbo_path) === $original->pbo_path) {
                $data['pbo_path'] = null;
            }
            return $data;
        }
        $source = self::source($campaignId, $original?->id);
        if (! $source && $original && $original->campaign_id == $campaignId && $original->pbo_path
            && filled($data['pbo_path'] ?? null) && basename($data['pbo_path']) !== basename($original->pbo_path)) {
            throw ValidationException::withMessages(['data.pbo_path' =>
                'Mantén el nombre exacto del PBO de la campaña persistente para conservar el progreso ALiVE.']);
        }
        if (! $source && $original && $original->campaign_id != $campaignId
            && str_starts_with((string) $original->pbo_key, 'campaign:')
            && ($data['pbo_path'] ?? $original->pbo_path) === $original->pbo_path) {
            $data['pbo_path'] = null; // Never silently link unrelated campaigns to one stored file.
        }
        if (! $source) { return $data; }
        if ($original && $original->campaign_id == $campaignId && $original->pbo_path
            && basename($original->pbo_path) !== basename($source->pbo_path)) {
            throw ValidationException::withMessages(['data.pbo_path' =>
                'La campaña contiene PBO con nombres distintos. Revisa sqa:share-pbos antes de editarla.']);
        }
        $newPath = $data['pbo_path'] ?? null;
        $uploaded = filled($newPath) && $newPath !== $original?->pbo_path && $newPath !== $source->pbo_path;
        if ($uploaded) {
            if (strcasecmp(basename($newPath), basename($source->pbo_path)) !== 0) {
                throw ValidationException::withMessages(['data.pbo_path' =>
                    'Esta campaña persistente ya utiliza ' . basename($source->pbo_path)
                    . '. Mantén el nombre exacto para no perder el progreso ALiVE.']);
            }
            if (basename($newPath) !== basename($source->pbo_path)) {
                throw ValidationException::withMessages(['data.pbo_path' =>
                    'El nombre debe coincidir exactamente con ' . basename($source->pbo_path) . '.']);
            }
            return $data;
        }
        // On selecting an existing persistent campaign, inherit its existing PBO.
        if (! $original || $original->campaign_id !== $campaignId || $original->pbo_path !== $source->pbo_path) {
            $data['pbo_path'] = $source->pbo_path;
            $data['pbo'] = $source->pbo;
        }
        return $data;
    }

    /** Metadata already persisted; reconcile all members without changing their addons or ORBAT. */
    public function reconcile(Activity $activity, ?string $oldKey, ?string $oldPath): void
    {
        $key = self::key($activity);
        if ($activity->pbo_key !== $key) {
            $activity->forceFill(['pbo_key' => $key])->save();
        }
        if (! str_starts_with($key, 'campaign:') || ! $activity->pbo_path) { return; }

        // Serialization point for simultaneous saves of the same persistent campaign.
        ActivityPboExport::query()->firstOrCreate(['pbo_key' => $key], ['status' => 'pending']);
        ActivityPboExport::query()->where('pbo_key', $key)->lockForUpdate()->first();

        $others = Activity::withTrashed()->where('campaign_id', $activity->campaign_id)
            ->whereKeyNot($activity->id)->lockForUpdate()->get();
        // Windows has one flat folder. Refuse collisions with another campaign/activity.
        if (Activity::query()->where(function ($q) use ($activity) {
                $q->where('campaign_id', '!=', $activity->campaign_id)->orWhereNull('campaign_id');
            })->whereRaw('LOWER(pbo) = ?', [strtolower(basename($activity->pbo_path))])->exists()) {
            throw ValidationException::withMessages(['data.pbo_path' =>
                'Otro PBO de una campaña o actividad independiente utiliza ya este nombre. No se puede sobrescribir en Windows.']);
        }
        foreach ($others as $other) {
            if ($other->pbo_path && basename($other->pbo_path) !== basename($activity->pbo_path)) {
                throw ValidationException::withMessages(['data.pbo_path' =>
                    'La campaña contiene PBO con nombres diferentes. Concílialos antes de guardar.']);
            }
        }
        $reference = $others->first(fn (Activity $a) => $a->pbo_path && $a->pbo_path !== $activity->pbo_path);
        if ($reference && Storage::disk('local')->exists($reference->pbo_path)
            && Storage::disk('local')->exists($activity->pbo_path)
            && hash_file('sha256', Storage::disk('local')->path($reference->pbo_path))
                === hash_file('sha256', Storage::disk('local')->path($activity->pbo_path))) {
            $redundant = $activity->pbo_path;
            $activity->forceFill([
                'pbo_path' => $reference->pbo_path,
                'pbo' => $reference->pbo,
                'pbo_size_bytes' => $reference->pbo_size_bytes,
                'pbo_uploaded_by' => $reference->pbo_uploaded_by,
                'pbo_uploaded_at' => $reference->pbo_uploaded_at,
            ])->save();
            self::cleanupPath($redundant, $activity->pbo_path);
        }
        foreach ($others as $other) {
            if ($other->pbo_path && basename($other->pbo_path) !== basename($activity->pbo_path)) {
                throw ValidationException::withMessages(['data.pbo_path' =>
                    'La campaña contiene PBO con nombres diferentes. Revisa las actividades y ejecuta primero la conciliación.']);
            }
            $affected = $other->pbo_path !== $activity->pbo_path
                || $other->pbo_size_bytes !== $activity->pbo_size_bytes
                || $other->pbo_uploaded_by !== $activity->pbo_uploaded_by
                || $other->pbo_uploaded_at != $activity->pbo_uploaded_at;
            if ($affected && ! ActivityTypeAccess::can(
                auth()->user(), 'activities', 'update', $other->activity_type_id
            )) {
                throw ValidationException::withMessages(['data.pbo_path' =>
                    'No puedes actualizar el PBO compartido: no tienes permiso para modificar ' . $other->name . '.']);
            }
        }
        foreach ($others as $other) {
            $fields = [
                'pbo_key' => $key,
                'pbo_path' => $activity->pbo_path,
                'pbo' => $activity->pbo,
                'pbo_size_bytes' => $activity->pbo_size_bytes,
                'pbo_uploaded_by' => $activity->pbo_uploaded_by,
                'pbo_uploaded_at' => $activity->pbo_uploaded_at,
            ];
            if (collect($fields)->contains(fn ($value, $column) => $other->{$column} != $value)) {
                $other->forceFill($fields)->save(); // Eloquent audit per affected activity.
            }
        }
    }

    public static function cleanupPath(?string $previous, ?string $current): void
    {
        if (! $previous || $previous === $current || ! ActivityPbo::isOwnStoredPath($previous)) { return; }
        DB::afterCommit(static function () use ($previous): void {
            if (! Activity::withTrashed()->where('pbo_path', $previous)->exists()) {
                $html = dirname($previous) . '/' . \App\Support\ActivityAddonsHtml::htmlFilename(basename($previous));
                Storage::disk('local')->delete([$previous, $html]);
            }
        });
    }
}
