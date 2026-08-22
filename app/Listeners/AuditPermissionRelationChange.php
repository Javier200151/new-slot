<?php

namespace App\Listeners;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Permission\Contracts\Permission as PermissionContract;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;

class AuditPermissionRelationChange
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(object $event): void
    {
        if ($event instanceof RoleAttachedEvent) {
            $this->logRoles(
                $event->model,
                $this->roleNames(
                    $event->rolesOrIds
                ),
                attached: true,
            );

            return;
        }

        if ($event instanceof RoleDetachedEvent) {
            $this->logRoles(
                $event->model,
                $this->roleNames(
                    $event->rolesOrIds
                ),
                attached: false,
            );

            return;
        }

        if ($event instanceof PermissionAttachedEvent) {
            $this->logPermissions(
                $event->model,
                $this->permissionNames(
                    $event->permissionsOrIds
                ),
                attached: true,
            );

            return;
        }

        if ($event instanceof PermissionDetachedEvent) {
            $this->logPermissions(
                $event->model,
                $this->permissionNames(
                    $event->permissionsOrIds
                ),
                attached: false,
            );
        }
    }

    private function logRoles(
        Model $model,
        array $changedRoles,
        bool $attached,
    ): void {
        $after = $model->roles()
            ->orderBy('name')
            ->pluck('name')
            ->values()
            ->all();

        $before = $attached
            ? array_values(
                array_diff(
                    $after,
                    $changedRoles
                )
            )
            : array_values(
                array_unique([
                    ...$after,
                    ...$changedRoles,
                ])
            );

        sort($before);
        sort($after);

        $this->auditLogger->change(
            subject: $model,

            event: $attached
                ? 'roles_attached'
                : 'roles_detached',

            old: [
                'roles' => $before,
            ],

            new: [
                'roles' => $after,
            ],

            properties: [
                'relation' => 'roles',

                'table' => config(
                    'permission.table_names.model_has_roles'
                ),

                'changed_roles' =>
                    $changedRoles,
            ],
        );
    }

    private function logPermissions(
        Model $model,
        array $changedPermissions,
        bool $attached,
    ): void {
        $after = $model->permissions()
            ->orderBy('name')
            ->pluck('name')
            ->values()
            ->all();

        $before = $attached
            ? array_values(
                array_diff(
                    $after,
                    $changedPermissions
                )
            )
            : array_values(
                array_unique([
                    ...$after,
                    ...$changedPermissions,
                ])
            );

        sort($before);
        sort($after);

        $table =
            $model instanceof RoleContract
                ? config(
                    'permission.table_names.role_has_permissions'
                )
                : config(
                    'permission.table_names.model_has_permissions'
                );

        $this->auditLogger->change(
            subject: $model,

            event: $attached
                ? 'permissions_attached'
                : 'permissions_detached',

            old: [
                'permissions' => $before,
            ],

            new: [
                'permissions' => $after,
            ],

            properties: [
                'relation' => 'permissions',
                'table' => $table,

                'changed_permissions' =>
                    $changedPermissions,
            ],
        );
    }

    private function roleNames(
        mixed $rolesOrIds
    ): array {
        return $this->resolveNames(
            $rolesOrIds,
            config('permission.models.role'),
            RoleContract::class,
        );
    }

    private function permissionNames(
        mixed $permissionsOrIds
    ): array {
        return $this->resolveNames(
            $permissionsOrIds,
            config(
                'permission.models.permission'
            ),
            PermissionContract::class,
        );
    }

    private function resolveNames(
        mixed $items,
        string $modelClass,
        string $contract,
    ): array {
        $items =
            $items instanceof Collection
                ? $items
                : collect(
                    is_array($items)
                        ? $items
                        : [$items]
                );

        $names = [];
        $ids = [];

        foreach ($items as $item) {
            if ($item instanceof $contract) {
                $names[] =
                    (string) $item->name;

                continue;
            }

            if (
                is_int($item)
                || is_string($item)
            ) {
                $ids[] = $item;
            }
        }

        if ($ids !== []) {
            $names = [
                ...$names,

                ...$modelClass::query()
                    ->whereIn('id', $ids)
                    ->pluck('name')
                    ->all(),
            ];
        }

        $names =
            array_values(
                array_unique($names)
            );

        sort($names);

        return $names;
    }
}