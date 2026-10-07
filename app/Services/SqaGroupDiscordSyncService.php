<?php

namespace App\Services;

use App\Models\MemberProcedureSetting;
use App\Models\SqaGroup;
use App\Models\SqaGroupUser;
use App\Models\User;
use App\Services\MemberProcedures\DiscordService;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SqaGroupDiscordSyncService
{
    public function __construct(private readonly DiscordService $discord)
    {
    }

    public function membershipAdded(SqaGroupUser $membership): void
    {
        $membership->loadMissing(['user', 'sqaGroup']);
        $this->safeAssign($membership->user, $membership->sqaGroup, 'Alta en grupo SQA');
    }

    public function membershipRemoved(SqaGroupUser $membership): void
    {
        $membership->loadMissing(['user', 'sqaGroup']);
        $this->safeRemove($membership->user, $membership->sqaGroup, 'Baja de grupo SQA');
    }

    public function membershipChanged(SqaGroupUser $membership): void
    {
        $oldUserId = (int) $membership->getOriginal('user_id');
        $oldGroupId = (int) $membership->getOriginal('sqa_group_id');

        $newUserId = (int) $membership->user_id;
        $newGroupId = (int) $membership->sqa_group_id;

        if ($oldUserId === $newUserId && $oldGroupId === $newGroupId) {
            return;
        }

        $oldUser = User::withTrashed()->find($oldUserId);
        $oldGroup = SqaGroup::withTrashed()->find($oldGroupId);
        if ($oldUser && $oldGroup) {
            $this->safeRemove($oldUser, $oldGroup, 'Cambio de asignación de grupo SQA');
        }

        $membership->loadMissing(['user', 'sqaGroup']);
        $this->safeAssign($membership->user, $membership->sqaGroup, 'Cambio de asignación de grupo SQA');
    }

    public function groupRoleChanged(SqaGroup $group, ?string $oldRoleId): void
    {
        $oldRoleId = $this->normaliseRoleId($oldRoleId);
        $newRoleId = $this->normaliseRoleId($group->discord_role_id);

        if ($oldRoleId === $newRoleId) {
            return;
        }

        $memberships = $group->sqaGroupUsers()->with('user')->get();
        foreach ($memberships as $membership) {
            $user = $membership->user;
            if (! $user) {
                continue;
            }

            if ($oldRoleId !== null) {
                $this->safeRemoveRole($user, $group, $oldRoleId, 'Cambio de rol Discord del grupo SQA');
            }

            if ($newRoleId !== null && ! $group->trashed()) {
                $this->safeAssignRole($user, $group, $newRoleId, 'Cambio de rol Discord del grupo SQA');
            }
        }
    }

    public function groupDeleted(SqaGroup $group): void
    {
        $roleId = $this->normaliseRoleId($group->discord_role_id);
        if ($roleId === null) {
            return;
        }

        foreach ($group->sqaGroupUsers()->with('user')->get() as $membership) {
            if ($membership->user) {
                $this->safeRemoveRole($membership->user, $group, $roleId, 'Grupo SQA desactivado');
            }
        }
    }

    public function groupRestored(SqaGroup $group): void
    {
        foreach ($group->sqaGroupUsers()->with('user')->get() as $membership) {
            if ($membership->user) {
                $this->safeAssign($membership->user, $group, 'Grupo SQA restaurado');
            }
        }
    }

    /** @return array{synced:int,skipped:int,errors:list<string>} */
    public function syncGroupNow(SqaGroup $group): array
    {
        $roleId = $this->normaliseRoleId($group->discord_role_id);
        if ($roleId === null) {
            throw new RuntimeException('Este Grupo SQA no tiene un rol de Discord configurado.');
        }

        $setting = $this->settingOrFail();
        $synced = 0;
        $skipped = 0;
        $errors = [];

        foreach ($group->sqaGroupUsers()->with('user')->get() as $membership) {
            $user = $membership->user;
            if (! $user || ! $this->hasUsableDiscordId($user)) {
                $skipped++;
                continue;
            }

            try {
                $this->discord->assignRole($user, $setting, $roleId, 'Sincronización manual del grupo SQA ' . $group->name);
                $synced++;
            } catch (Throwable $e) {
                $errors[] = ($user->nick ?: ('ID ' . $user->id)) . ': ' . $e->getMessage();
            }
        }

        return compact('synced', 'skipped', 'errors');
    }

    private function safeAssign(?User $user, ?SqaGroup $group, string $reason): void
    {
        if (! $user || ! $group || $group->trashed()) {
            return;
        }

        $roleId = $this->normaliseRoleId($group->discord_role_id);
        if ($roleId === null) {
            return;
        }

        $this->safeAssignRole($user, $group, $roleId, $reason);
    }

    private function safeRemove(?User $user, ?SqaGroup $group, string $reason): void
    {
        if (! $user || ! $group) {
            return;
        }

        $roleId = $this->normaliseRoleId($group->discord_role_id);
        if ($roleId === null) {
            return;
        }

        $this->safeRemoveRole($user, $group, $roleId, $reason);
    }

    private function safeAssignRole(User $user, SqaGroup $group, string $roleId, string $reason): void
    {
        $setting = $this->settingIfReady();
        if (! $setting || ! $this->hasUsableDiscordId($user)) {
            return;
        }

        try {
            $this->discord->assignRole($user, $setting, $roleId, $reason . ': ' . $group->name);
        } catch (Throwable $e) {
            $this->logFailure('assign', $user, $group, $roleId, $e);
        }
    }

    private function safeRemoveRole(User $user, SqaGroup $group, string $roleId, string $reason): void
    {
        if ($this->anotherActiveGroupKeepsRole($user, $group, $roleId)) {
            return;
        }

        $setting = $this->settingIfReady();
        if (! $setting || ! $this->hasUsableDiscordId($user)) {
            return;
        }

        try {
            $this->discord->removeRole($user, $setting, $roleId, $reason . ': ' . $group->name, allowMissingMember: true);
        } catch (Throwable $e) {
            $this->logFailure('remove', $user, $group, $roleId, $e);
        }
    }

    private function anotherActiveGroupKeepsRole(User $user, SqaGroup $excludedGroup, string $roleId): bool
    {
        return SqaGroupUser::query()
            ->where('user_id', $user->getKey())
            ->where('sqa_group_id', '!=', $excludedGroup->getKey())
            ->whereHas('sqaGroup', fn ($query) => $query->where('discord_role_id', $roleId))
            ->exists();
    }

    private function settingIfReady(): ?MemberProcedureSetting
    {
        if (! (bool) config('newslot.procedures.discord.enabled') || blank(config('newslot.procedures.discord.bot_token'))) {
            return null;
        }

        $setting = MemberProcedureSetting::current();

        return filled($setting->discord_guild_id) ? $setting : null;
    }

    private function settingOrFail(): MemberProcedureSetting
    {
        $setting = $this->settingIfReady();
        if (! $setting) {
            throw new RuntimeException('Discord no está configurado o falta seleccionar el servidor en Config. procedimientos.');
        }

        return $setting;
    }

    private function hasUsableDiscordId(User $user): bool
    {
        return preg_match('/^\d{17,20}$/', trim((string) $user->discord_id)) === 1;
    }

    private function normaliseRoleId(?string $roleId): ?string
    {
        $roleId = trim((string) $roleId);

        return preg_match('/^\d{17,20}$/', $roleId) === 1 ? $roleId : null;
    }

    private function logFailure(string $operation, User $user, SqaGroup $group, string $roleId, Throwable $e): void
    {
        Log::warning('No se pudo sincronizar un Grupo SQA con Discord.', [
            'operation' => $operation,
            'user_id' => $user->getKey(),
            'sqa_group_id' => $group->getKey(),
            'discord_role_id' => $roleId,
            'error' => $e->getMessage(),
        ]);
    }
}
