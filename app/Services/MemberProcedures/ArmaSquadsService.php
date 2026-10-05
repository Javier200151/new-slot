<?php

namespace App\Services\MemberProcedures;

use App\Models\MemberProcedureSetting;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ArmaSquadsService
{
    public function isConfigured(MemberProcedureSetting $setting): bool
    {
        return (bool) config('newslot.procedures.armasquads.enabled')
            && filled(config('newslot.procedures.armasquads.api_key'))
            && filled($setting->armasquads_squad_id);
    }

    /** @return array<string, mixed> */
    public function upsert(User $user, MemberProcedureSetting $setting): array
    {
        if (! $this->isConfigured($setting)) {
            throw new RuntimeException('ArmaSquads no está configurado todavía.');
        }

        if (blank($user->steam_id)) {
            throw new RuntimeException('El usuario no tiene SteamID64.');
        }

        $squadId = rawurlencode((string) $setting->armasquads_squad_id);
        $uuid = rawurlencode((string) $user->steam_id);
        $path = "/squads/{$squadId}/members/{$uuid}";
        $payload = ['uuid' => (string) $user->steam_id, 'username' => (string) $user->nick];

        $response = $this->client()->get($path);

        if ($response->status() === 404) {
            $created = $this->client()->post("/squads/{$squadId}/members", $payload);
            if (! $created->successful()) {
                throw new RuntimeException('ArmaSquads rechazó el alta del miembro (HTTP ' . $created->status() . ').');
            }

            return ['action' => 'created', 'http_status' => $created->status()];
        }

        if (! $response->successful()) {
            throw new RuntimeException('No se pudo consultar el miembro en ArmaSquads (HTTP ' . $response->status() . ').');
        }

        $remote = $response->json();
        $remoteUsername = is_array($remote) ? (string) ($remote['username'] ?? '') : '';

        if ($remoteUsername !== '' && $remoteUsername === (string) $user->nick) {
            return ['action' => 'already_synced', 'http_status' => $response->status()];
        }

        $updated = $this->client()->put($path, $payload);
        if (! $updated->successful()) {
            throw new RuntimeException('ArmaSquads rechazó la actualización del miembro (HTTP ' . $updated->status() . ').');
        }

        return ['action' => 'updated', 'http_status' => $updated->status()];
    }

    /** @return array<string, mixed> */
    public function delete(User $user, MemberProcedureSetting $setting): array
    {
        if (! $this->isConfigured($setting)) {
            throw new RuntimeException('ArmaSquads no está configurado todavía.');
        }

        if (blank($user->steam_id)) {
            return ['action' => 'nothing_to_delete'];
        }

        $squadId = rawurlencode((string) $setting->armasquads_squad_id);
        $uuid = rawurlencode((string) $user->steam_id);
        $response = $this->client()->delete("/squads/{$squadId}/members/{$uuid}");

        if ($response->status() === 404) {
            return ['action' => 'already_absent', 'http_status' => 404];
        }

        if (! $response->successful()) {
            throw new RuntimeException('ArmaSquads rechazó la baja del miembro (HTTP ' . $response->status() . ').');
        }

        return ['action' => 'deleted', 'http_status' => $response->status()];
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('newslot.procedures.armasquads.base_url'), '/'))
            ->withHeaders(['X-API-Key' => (string) config('newslot.procedures.armasquads.api_key')])
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('newslot.procedures.armasquads.timeout', 10));
    }
}
