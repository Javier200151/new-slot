<?php

namespace App\Services\MemberProcedures;

use App\Models\HomepageSetting;
use App\Models\MemberProcedureSetting;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DiscordService
{
    private const PERMISSION_CREATE_INSTANT_INVITE = 1 << 0;
    private const PERMISSION_BAN_MEMBERS = 1 << 2;
    private const PERMISSION_ADMINISTRATOR = 1 << 3;
    private const PERMISSION_VIEW_CHANNEL = 1 << 10;
    private const PERMISSION_CHANGE_NICKNAME = 1 << 26;
    private const PERMISSION_MANAGE_NICKNAMES = 1 << 27;
    private const PERMISSION_MANAGE_ROLES = 1 << 28;

    public function isConfigured(MemberProcedureSetting $setting): bool
    {
        return (bool) config('newslot.procedures.discord.enabled')
            && filled(config('newslot.procedures.discord.bot_token'))
            && filled($setting->discord_guild_id)
            && filled($setting->discord_recruit_role_id)
            && filled($setting->discord_alpha_role_id)
            && filled($setting->discord_reserve_role_id);
    }

    /** @return array<string, mixed> */
    public function testConnection(MemberProcedureSetting $setting): array
    {
        $this->assertConfigured($setting);

        $me = $this->expectJson($this->client()->get('/users/@me'), 'No se pudo identificar el bot de Discord');
        $guild = $this->expectJson(
            $this->client()->get('/guilds/' . rawurlencode((string) $setting->discord_guild_id)),
            'No se pudo acceder al servidor de Discord configurado',
        );
        $rolesResponse = $this->client()->get('/guilds/' . rawurlencode((string) $setting->discord_guild_id) . '/roles');
        if (! $rolesResponse->successful() || ! is_array($rolesResponse->json())) {
            throw new RuntimeException($this->errorMessage($rolesResponse, 'No se pudieron consultar los roles del servidor de Discord'));
        }

        /** @var array<int, array<string, mixed>> $roles */
        $roles = $rolesResponse->json();
        $rolesById = collect($roles)->keyBy(fn (array $role): string => (string) ($role['id'] ?? ''));

        $configuredRoles = [
            'RECLUTA' => (string) $setting->discord_recruit_role_id,
            'ALPHA' => (string) $setting->discord_alpha_role_id,
            'RESERVA' => (string) $setting->discord_reserve_role_id,
        ];

        $roleSummary = [];
        foreach ($configuredRoles as $label => $roleId) {
            $role = $rolesById->get($roleId);
            if (! is_array($role)) {
                throw new RuntimeException("El Role ID configurado para {$label} no existe en el servidor de Discord.");
            }
            if ((bool) ($role['managed'] ?? false)) {
                throw new RuntimeException("El rol {$label} está gestionado por una integración de Discord y no puede asignarse manualmente por el bot.");
            }

            $roleSummary[$label] = [
                'id' => $roleId,
                'name' => (string) ($role['name'] ?? $label),
                'position' => (int) ($role['position'] ?? 0),
            ];
        }

        $botId = (string) ($me['id'] ?? '');
        if ($botId === '') {
            throw new RuntimeException('Discord no devolvió el ID del bot.');
        }

        $member = $this->expectJson(
            $this->client()->get('/guilds/' . rawurlencode((string) $setting->discord_guild_id) . '/members/' . rawurlencode($botId)),
            'El bot no aparece como miembro del servidor de Discord',
        );

        $botRoleIds = array_values(array_unique(array_merge(
            [(string) $setting->discord_guild_id],
            array_map('strval', (array) ($member['roles'] ?? [])),
        )));

        $permissions = 0;
        $highestPosition = 0;
        foreach ($botRoleIds as $roleId) {
            $role = $rolesById->get($roleId);
            if (! is_array($role)) {
                continue;
            }

            $permissions |= (int) ((string) ($role['permissions'] ?? '0'));
            $highestPosition = max($highestPosition, (int) ($role['position'] ?? 0));
        }

        $isAdministrator = $this->hasPermission($permissions, self::PERMISSION_ADMINISTRATOR);
        $canManageRoles = $isAdministrator || $this->hasPermission($permissions, self::PERMISSION_MANAGE_ROLES);
        $canManageNicknames = $isAdministrator || $this->hasPermission($permissions, self::PERMISSION_MANAGE_NICKNAMES);
        $canChangeNickname = $isAdministrator || $this->hasPermission($permissions, self::PERMISSION_CHANGE_NICKNAME);
        $canBanMembers = $isAdministrator || $this->hasPermission($permissions, self::PERMISSION_BAN_MEMBERS);
        $canCreateInvite = $isAdministrator || $this->hasPermission($permissions, self::PERMISSION_CREATE_INSTANT_INVITE);
        $canViewChannel = $isAdministrator || $this->hasPermission($permissions, self::PERMISSION_VIEW_CHANNEL);

        if (! $canManageRoles) {
            throw new RuntimeException('El bot no tiene el permiso Gestionar roles (MANAGE_ROLES).');
        }
        if (! $canManageNicknames) {
            throw new RuntimeException('El bot no tiene el permiso Gestionar apodos (MANAGE_NICKNAMES), necesario para aplicar [=ALPHA=] al apodo de los miembros.');
        }
        if (! $canBanMembers) {
            throw new RuntimeException('El bot no tiene el permiso Banear miembros (BAN_MEMBERS), necesario para los ceses que requieran baneo.');
        }

        foreach ($roleSummary as $label => $role) {
            if ($highestPosition <= (int) $role['position']) {
                throw new RuntimeException("El rol del bot debe estar por encima del rol {$label} en la jerarquía de Discord.");
            }
        }

        $inviteChannel = null;
        if (filled($setting->discord_invite_channel_id)) {
            if (! $canCreateInvite) {
                throw new RuntimeException('El bot no tiene el permiso Crear invitación instantánea (CREATE_INSTANT_INVITE).');
            }
            if (! $canViewChannel) {
                throw new RuntimeException('El bot no tiene el permiso Ver canales (VIEW_CHANNEL), necesario para acceder al canal de invitación configurado.');
            }

            $channel = $this->expectJson(
                $this->client()->get('/channels/' . rawurlencode((string) $setting->discord_invite_channel_id)),
                'No se pudo acceder al canal configurado para la invitación pública',
            );

            if ((string) ($channel['guild_id'] ?? '') !== (string) $setting->discord_guild_id) {
                throw new RuntimeException('El Canal ID de invitación no pertenece al Guild ID configurado.');
            }

            $inviteChannel = [
                'id' => (string) ($channel['id'] ?? $setting->discord_invite_channel_id),
                'name' => (string) ($channel['name'] ?? $setting->discord_invite_channel_id),
            ];
        }

        if (filled($setting->discord_bot_nickname) && ! $canChangeNickname) {
            throw new RuntimeException('El bot no tiene el permiso Cambiar apodo (CHANGE_NICKNAME), necesario para modificar su propio apodo desde Filament.');
        }

        return [
            'bot_id' => $botId,
            'bot_name' => (string) ($me['global_name'] ?? $me['username'] ?? $botId),
            'bot_nickname' => $member['nick'] ?? null,
            'guild_id' => (string) ($guild['id'] ?? $setting->discord_guild_id),
            'guild_name' => (string) ($guild['name'] ?? $setting->discord_guild_id),
            'roles' => $roleSummary,
            'invite_channel' => $inviteChannel,
            'manage_roles' => true,
            'manage_nicknames' => true,
            'change_nickname' => $canChangeNickname,
            'create_invite' => $canCreateInvite,
            'ban_members' => $canBanMembers,
        ];
    }

    /** @return array<string, string> */
    public function guildOptions(): array
    {
        $this->assertApiConfigured();

        $guilds = Cache::remember(
            $this->catalogCacheKey('guilds'),
            now()->addMinute(),
            function (): array {
                $response = $this->client()->get('/users/@me/guilds');
                if (! $response->successful() || ! is_array($response->json())) {
                    throw new RuntimeException($this->errorMessage($response, 'No se pudieron consultar los servidores del bot en Discord'));
                }

                return array_values(array_filter(
                    $response->json(),
                    static fn (mixed $guild): bool => is_array($guild) && filled($guild['id'] ?? null),
                ));
            },
        );

        usort($guilds, static fn (array $a, array $b): int => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));

        $options = [];
        foreach ($guilds as $guild) {
            $id = (string) ($guild['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $options[$id] = (string) ($guild['name'] ?? $id);
        }

        return $options;
    }

    /** @return array<string, string> */
    public function roleOptions(?string $guildId): array
    {
        $this->assertApiConfigured();
        $guildId = $this->validateSnowflake($guildId, 'Guild ID');

        $roles = Cache::remember(
            $this->catalogCacheKey('roles:' . $guildId),
            now()->addMinute(),
            function () use ($guildId): array {
                $response = $this->client()->get('/guilds/' . rawurlencode($guildId) . '/roles');
                if (! $response->successful() || ! is_array($response->json())) {
                    throw new RuntimeException($this->errorMessage($response, 'No se pudieron consultar los roles del servidor de Discord'));
                }

                return array_values(array_filter($response->json(), static fn (mixed $role): bool => is_array($role)));
            },
        );

        usort($roles, static function (array $a, array $b): int {
            $positionCompare = ((int) ($b['position'] ?? 0)) <=> ((int) ($a['position'] ?? 0));

            return $positionCompare !== 0
                ? $positionCompare
                : strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        $options = [];
        foreach ($roles as $role) {
            $id = (string) ($role['id'] ?? '');
            if ($id === '' || $id === $guildId || (bool) ($role['managed'] ?? false)) {
                continue;
            }

            $name = trim((string) ($role['name'] ?? $id));
            $options[$id] = $name !== '' ? $name : $id;
        }

        return $options;
    }

    /** @return array<string, string> */
    public function channelOptions(?string $guildId): array
    {
        $this->assertApiConfigured();
        $guildId = $this->validateSnowflake($guildId, 'Guild ID');

        $channels = Cache::remember(
            $this->catalogCacheKey('channels:' . $guildId),
            now()->addMinute(),
            function () use ($guildId): array {
                $response = $this->client()->get('/guilds/' . rawurlencode($guildId) . '/channels');
                if (! $response->successful() || ! is_array($response->json())) {
                    throw new RuntimeException($this->errorMessage($response, 'No se pudieron consultar los canales del servidor de Discord'));
                }

                return array_values(array_filter($response->json(), static fn (mixed $channel): bool => is_array($channel)));
            },
        );

        $inviteCapableTypes = [0, 2, 5, 13, 15, 16];
        $typeLabels = [
            0 => 'Texto',
            2 => 'Voz',
            5 => 'Anuncios',
            13 => 'Escenario',
            15 => 'Foro',
            16 => 'Media',
        ];

        $channels = array_values(array_filter(
            $channels,
            static fn (array $channel): bool => in_array((int) ($channel['type'] ?? -1), $inviteCapableTypes, true),
        ));

        usort($channels, static function (array $a, array $b): int {
            $positionCompare = ((int) ($a['position'] ?? 0)) <=> ((int) ($b['position'] ?? 0));

            return $positionCompare !== 0
                ? $positionCompare
                : strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        $options = [];
        foreach ($channels as $channel) {
            $id = (string) ($channel['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $type = (int) ($channel['type'] ?? -1);
            $name = trim((string) ($channel['name'] ?? $id));
            $prefix = in_array($type, [0, 5, 15, 16], true) ? '#' : '';
            $options[$id] = ($typeLabels[$type] ?? 'Canal') . ' · ' . $prefix . ($name !== '' ? $name : $id);
        }

        return $options;
    }

    public function clearCatalogCache(?string $guildId = null): void
    {
        Cache::forget($this->catalogCacheKey('guilds'));

        if (filled($guildId) && preg_match('/^\d{17,20}$/', (string) $guildId)) {
            Cache::forget($this->catalogCacheKey('roles:' . $guildId));
            Cache::forget($this->catalogCacheKey('channels:' . $guildId));
        }
    }

    /** @return array<string, mixed> */
    public function setRecruit(User $user, MemberProcedureSetting $setting, string $reason): array
    {
        return $this->syncRoles(
            $user,
            $setting,
            (string) $setting->discord_recruit_role_id,
            [(string) $setting->discord_alpha_role_id, (string) $setting->discord_reserve_role_id],
            $reason,
            targetNickname: $this->plainNickname($user),
        );
    }

    /** @return array<string, mixed> */
    public function setAlpha(User $user, MemberProcedureSetting $setting, string $reason): array
    {
        return $this->syncRoles(
            $user,
            $setting,
            (string) $setting->discord_alpha_role_id,
            [(string) $setting->discord_recruit_role_id, (string) $setting->discord_reserve_role_id],
            $reason,
            targetNickname: $this->alphaNickname($user, $setting),
        );
    }

    /** @return array<string, mixed> */
    public function setReserve(User $user, MemberProcedureSetting $setting, string $reason): array
    {
        return $this->syncRoles(
            $user,
            $setting,
            (string) $setting->discord_reserve_role_id,
            [(string) $setting->discord_recruit_role_id, (string) $setting->discord_alpha_role_id],
            $reason,
            targetNickname: $this->plainNickname($user),
        );
    }

    /** @return array<string, mixed> */
    public function assignRole(User $user, MemberProcedureSetting $setting, string $roleId, string $reason): array
    {
        $roleId = $this->validateSnowflake($roleId, 'Role ID');

        return $this->syncRoles(
            $user,
            $setting,
            $roleId,
            [],
            $reason,
            requireLifecycleRoleConfig: false,
        );
    }

    /** @return array<string, mixed> */
    public function removeRole(
        User $user,
        MemberProcedureSetting $setting,
        string $roleId,
        string $reason,
        bool $allowMissingMember = true,
    ): array {
        $roleId = $this->validateSnowflake($roleId, 'Role ID');

        return $this->syncRoles(
            $user,
            $setting,
            null,
            [$roleId],
            $reason,
            allowMissingMember: $allowMissingMember,
            requireLifecycleRoleConfig: false,
        );
    }

    /** @return array<string, mixed> */
    public function removeManagedRoles(User $user, MemberProcedureSetting $setting, string $reason): array
    {
        return $this->syncRoles(
            $user,
            $setting,
            null,
            [
                (string) $setting->discord_recruit_role_id,
                (string) $setting->discord_alpha_role_id,
                (string) $setting->discord_reserve_role_id,
            ],
            $reason,
            allowMissingMember: true,
            targetNickname: $this->plainNickname($user),
        );
    }

    /** @return array<string, mixed> */
    public function ban(User $user, MemberProcedureSetting $setting, string $reason): array
    {
        $this->assertConfigured($setting);
        $discordId = $this->discordId($user);
        $guildId = rawurlencode((string) $setting->discord_guild_id);
        $encodedUserId = rawurlencode($discordId);
        $path = "/guilds/{$guildId}/bans/{$encodedUserId}";

        $existing = $this->client()->get($path);
        if ($existing->successful()) {
            return ['action' => 'already_banned', 'discord_user_id' => $discordId];
        }
        if ($existing->status() !== 404) {
            throw new RuntimeException($this->errorMessage($existing, 'No se pudo comprobar si el usuario ya estaba baneado en Discord'));
        }

        $response = $this->client($reason)->put($path, ['delete_message_seconds' => 0]);
        if (! $response->successful()) {
            throw new RuntimeException($this->errorMessage($response, 'Discord rechazó el baneo del usuario'));
        }

        return [
            'action' => 'banned',
            'discord_user_id' => $discordId,
            'http_status' => $response->status(),
        ];
    }

    /** @return array<string, mixed> */
    public function applyBotNickname(MemberProcedureSetting $setting): array
    {
        $this->assertBaseConfigured($setting);

        $nickname = $this->limitNickname((string) $setting->discord_bot_nickname);
        if ($nickname === '') {
            throw new RuntimeException('Configura primero el apodo del bot en Config. procedimientos → Discord.');
        }

        $guildId = rawurlencode((string) $setting->discord_guild_id);
        $response = $this->client('Cambio manual del apodo del bot desde NewSlot')
            ->patch("/guilds/{$guildId}/members/@me", ['nick' => $nickname]);

        $member = $this->expectJson($response, 'Discord rechazó el cambio de apodo del bot');

        return [
            'nickname' => (string) ($member['nick'] ?? $nickname),
            'http_status' => $response->status(),
        ];
    }

    /** @return array<string, mixed> */
    public function refreshPublicInvite(MemberProcedureSetting $setting, HomepageSetting $homepage): array
    {
        $this->assertBaseConfigured($setting);

        $channelId = trim((string) $setting->discord_invite_channel_id);
        if (! preg_match('/^\d{17,20}$/', $channelId)) {
            throw new RuntimeException('Configura un Canal ID numérico válido para la invitación pública de Discord.');
        }

        $response = $this->client('Renovación de la invitación pública de NewSlot')
            ->post('/channels/' . rawurlencode($channelId) . '/invites', [
                'max_age' => 604800,
                'max_uses' => 0,
                'temporary' => false,
                'unique' => true,
            ]);

        $invite = $this->expectJson($response, 'Discord rechazó la creación de la invitación pública');
        $code = trim((string) ($invite['code'] ?? ''));
        if ($code === '') {
            throw new RuntimeException('Discord creó la invitación pero no devolvió su código.');
        }

        $url = 'https://discord.gg/' . $code;
        $refreshedAt = now();
        $expiresAt = filled($invite['expires_at'] ?? null)
            ? \Illuminate\Support\Carbon::parse((string) $invite['expires_at'])
            : $refreshedAt->copy()->addDays(7);

        $homepage->forceFill([
            'discord_invite_url' => $url,
            'discord_invite_refreshed_at' => $refreshedAt,
            'discord_invite_expires_at' => $expiresAt,
        ])->save();

        return [
            'url' => $url,
            'code' => $code,
            'refreshed_at' => $refreshedAt,
            'expires_at' => $expiresAt,
            'http_status' => $response->status(),
        ];
    }

    /**
     * @param list<string> $removeRoleIds
     * @return array<string, mixed>
     */
    private function syncRoles(
        User $user,
        MemberProcedureSetting $setting,
        ?string $addRoleId,
        array $removeRoleIds,
        string $reason,
        bool $allowMissingMember = false,
        ?string $targetNickname = null,
        bool $requireLifecycleRoleConfig = true,
    ): array {
        if ($requireLifecycleRoleConfig) {
            $this->assertConfigured($setting);
        } else {
            $this->assertBaseConfigured($setting);
        }
        $discordId = $this->discordId($user);
        $guildId = rawurlencode((string) $setting->discord_guild_id);
        $encodedUserId = rawurlencode($discordId);
        $memberPath = "/guilds/{$guildId}/members/{$encodedUserId}";

        $memberResponse = $this->client()->get($memberPath);
        if ($memberResponse->status() === 404 && $allowMissingMember) {
            return [
                'action' => 'member_already_absent',
                'discord_user_id' => $discordId,
                'added_role_id' => null,
                'removed_role_ids' => [],
                'nickname' => null,
                'nickname_changed' => false,
            ];
        }
        if (! $memberResponse->successful()) {
            throw new RuntimeException($this->errorMessage(
                $memberResponse,
                $memberResponse->status() === 404
                    ? 'El usuario no pertenece al servidor de Discord configurado'
                    : 'No se pudo consultar al miembro en Discord',
            ));
        }

        $member = $memberResponse->json();
        $currentRoleIds = array_map('strval', is_array($member) ? (array) ($member['roles'] ?? []) : []);
        $currentNickname = is_array($member) ? (string) ($member['nick'] ?? '') : '';
        $removeRoleIds = array_values(array_unique(array_filter(array_map('strval', $removeRoleIds))));
        $removed = [];
        $added = null;

        foreach ($removeRoleIds as $roleId) {
            if ($roleId === '' || ! in_array($roleId, $currentRoleIds, true)) {
                continue;
            }

            $response = $this->client($reason)->delete(
                "/guilds/{$guildId}/members/{$encodedUserId}/roles/" . rawurlencode($roleId),
            );
            if (! $response->successful()) {
                throw new RuntimeException($this->errorMessage($response, 'Discord rechazó la retirada de un rol'));
            }
            $removed[] = $roleId;
        }

        if ($addRoleId !== null && $addRoleId !== '' && ! in_array($addRoleId, $currentRoleIds, true)) {
            $response = $this->client($reason)->send(
                'PUT',
                "/guilds/{$guildId}/members/{$encodedUserId}/roles/" . rawurlencode($addRoleId),
            );
            if (! $response->successful()) {
                throw new RuntimeException($this->errorMessage($response, 'Discord rechazó la asignación de un rol'));
            }
            $added = $addRoleId;
        }

        if ($removed !== [] || $added !== null) {
            $verification = $this->client()->get($memberPath);
            if (! $verification->successful()) {
                throw new RuntimeException($this->errorMessage($verification, 'No se pudo verificar el resultado del cambio de roles en Discord'));
            }

            $verified = $verification->json();
            $verifiedRoleIds = array_map('strval', is_array($verified) ? (array) ($verified['roles'] ?? []) : []);
            if ($addRoleId !== null && $addRoleId !== '' && ! in_array($addRoleId, $verifiedRoleIds, true)) {
                throw new RuntimeException('Discord respondió correctamente, pero el rol esperado no aparece al verificar el miembro.');
            }
            foreach ($removeRoleIds as $roleId) {
                if ($roleId !== '' && in_array($roleId, $verifiedRoleIds, true)) {
                    throw new RuntimeException('Discord respondió correctamente, pero uno de los roles que debía retirarse sigue asignado.');
                }
            }
        }

        $nicknameChanged = false;
        $finalNickname = $currentNickname;
        if ($targetNickname !== null) {
            $targetNickname = $this->limitNickname($targetNickname);
            if ($targetNickname !== '' && $currentNickname !== $targetNickname) {
                $nicknameResponse = $this->client($reason)->patch($memberPath, ['nick' => $targetNickname]);
                $updatedMember = $this->expectJson($nicknameResponse, 'Discord rechazó el cambio de apodo del miembro');
                $finalNickname = (string) ($updatedMember['nick'] ?? $targetNickname);
                if ($finalNickname !== $targetNickname) {
                    throw new RuntimeException('Discord respondió correctamente, pero el apodo esperado no aparece al verificar el miembro.');
                }
                $nicknameChanged = true;
            }
        }

        return [
            'action' => ($removed === [] && $added === null && ! $nicknameChanged) ? 'already_synced' : 'roles_updated',
            'discord_user_id' => $discordId,
            'added_role_id' => $added,
            'removed_role_ids' => $removed,
            'nickname' => $finalNickname,
            'nickname_changed' => $nicknameChanged,
        ];
    }

    private function assertConfigured(MemberProcedureSetting $setting): void
    {
        $this->assertBaseConfigured($setting);

        if (blank($setting->discord_recruit_role_id)
            || blank($setting->discord_alpha_role_id)
            || blank($setting->discord_reserve_role_id)) {
            throw new RuntimeException('Configura los Role ID de RECLUTA, ALPHA y RESERVA en Config. procedimientos.');
        }
    }

    private function assertBaseConfigured(MemberProcedureSetting $setting): void
    {
        $this->assertApiConfigured();

        if (blank($setting->discord_guild_id)) {
            throw new RuntimeException('Falta configurar el servidor de Discord en Config. procedimientos.');
        }
    }

    private function assertApiConfigured(): void
    {
        if (! (bool) config('newslot.procedures.discord.enabled')) {
            throw new RuntimeException('Discord está desactivado. Configura DISCORD_ENABLED=true.');
        }
        if (blank(config('newslot.procedures.discord.bot_token'))) {
            throw new RuntimeException('Falta DISCORD_BOT_TOKEN en el entorno del servidor.');
        }
    }

    private function validateSnowflake(?string $value, string $label): string
    {
        $value = trim((string) $value);
        if (! preg_match('/^\d{17,20}$/', $value)) {
            throw new RuntimeException($label . ' no es un ID numérico válido de Discord.');
        }

        return $value;
    }

    private function catalogCacheKey(string $suffix): string
    {
        $tokenHash = sha1((string) config('newslot.procedures.discord.bot_token'));

        return 'newslot:discord:catalog:' . $tokenHash . ':' . $suffix;
    }

    private function discordId(User $user): string
    {
        $discordId = trim((string) $user->discord_id);
        if (! preg_match('/^\d{17,20}$/', $discordId)) {
            throw new RuntimeException('El usuario no tiene un Discord ID numérico válido. Debe ser el ID de usuario de Discord, no el nombre de usuario.');
        }

        return $discordId;
    }

    private function plainNickname(User $user): string
    {
        return $this->limitNickname((string) $user->nick);
    }

    private function alphaNickname(User $user, MemberProcedureSetting $setting): string
    {
        $prefix = trim((string) ($setting->discord_alpha_nickname_prefix ?: '[=ALPHA=]'));
        $nickname = $prefix === ''
            ? (string) $user->nick
            : $prefix . ' ' . ltrim((string) $user->nick);

        return $this->limitNickname($nickname);
    }

    private function limitNickname(string $nickname): string
    {
        $nickname = trim($nickname);

        return function_exists('mb_substr')
            ? mb_substr($nickname, 0, 32)
            : substr($nickname, 0, 32);
    }

    private function hasPermission(int $permissions, int $permission): bool
    {
        return ($permissions & $permission) === $permission;
    }

    /** @return array<string, mixed> */
    private function expectJson(Response $response, string $message): array
    {
        if (! $response->successful() || ! is_array($response->json())) {
            throw new RuntimeException($this->errorMessage($response, $message));
        }

        return $response->json();
    }

    private function errorMessage(Response $response, string $prefix): string
    {
        $remote = $response->json();
        $detail = is_array($remote) ? trim((string) ($remote['message'] ?? '')) : '';

        return $prefix . ' (HTTP ' . $response->status() . ')' . ($detail !== '' ? ': ' . $detail : '') . '.';
    }

    private function client(?string $auditReason = null): PendingRequest
    {
        $request = Http::baseUrl(rtrim((string) config('newslot.procedures.discord.base_url', 'https://discord.com/api/v10'), '/'))
            ->withToken((string) config('newslot.procedures.discord.bot_token'), 'Bot')
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('newslot.procedures.discord.timeout', 10));

        if (filled($auditReason)) {
            $reason = trim((string) $auditReason);
            $reason = function_exists('mb_substr') ? mb_substr($reason, 0, 480) : substr($reason, 0, 480);

            $request = $request->withHeaders([
                'X-Audit-Log-Reason' => rawurlencode($reason),
            ]);
        }

        return $request;
    }
}
