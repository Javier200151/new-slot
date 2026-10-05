<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class ForumCategory extends Model
{
    use Auditable;

    public const TYPE_STANDARD = 'standard';
    public const TYPE_DIARY = 'diary';

    // Valores antiguos conservados únicamente para compatibilidad con datos
    // históricos. Las categorías normales ya no dependen de estos tipos.
    public const TYPE_CANTINA = 'cantina';
    public const TYPE_DEBATE = 'debate';
    public const TYPE_CALL = 'call';
    public const TYPE_PROPOSAL = 'proposal';
    public const TYPE_CONSULTATION = 'consultation';

    public const PERMISSION_ACTIONS = [
        'create' => 'Publicar nuevos hilos',
        'reply' => 'Responder a hilos',
        'poll' => 'Crear y gestionar votaciones',
        'moderate' => 'Cerrar, reabrir y fijar hilos',
        'delete' => 'Eliminar hilos y respuestas',
    ];

    public const RESERVED_SLUGS = [
        'categoria',
        'changelog',
        'diario',
        'nuevos-mensajes',
        'personal',
    ];

    protected $table = 'community_forum_categories';

    protected $fillable = [
        'slug',
        'title',
        'singular',
        'description',
        'hint',
        'icon',
        'color',
        'channel',
        'system_type',
        'process_type',
        'is_system',
        'is_enabled',
        'allow_polls',
        'sort_order',
    ];

    protected ?string $previousPermissionResource = null;

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_enabled' => 'boolean',
            'allow_polls' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ForumCategory $category): void {
            if (blank($category->slug)) {
                $category->slug = static::uniqueSlug((string) $category->title);
            }

            if (blank($category->singular)) {
                $category->singular = (string) $category->title;
            }

            if ($category->system_type === self::TYPE_DIARY) {
                $category->slug = 'diario';
                $category->channel = 'diary';
                $category->is_system = true;
                $category->allow_polls = false;

                return;
            }

            $category->channel = 'personal';
            $category->system_type = self::TYPE_STANDARD;
            $category->is_system = false;
        });

        static::updating(function (ForumCategory $category): void {
            if ($category->isDiary()) {
                // Diario es una categoría interna. Permitimos personalizar
                // nombre visible, singular, color y orden; los estados se
                // guardan en su relación independiente. Su identidad y lógica
                // interna permanecen protegidas aunque el request intente
                // alterar simultáneamente slug/system_type.
                foreach ([
                    'slug',
                    'description',
                    'hint',
                    'icon',
                    'channel',
                    'system_type',
                    'process_type',
                    'is_system',
                    'is_enabled',
                    'allow_polls',
                ] as $protectedField) {
                    if ($category->isDirty($protectedField)) {
                        $category->setAttribute($protectedField, $category->getOriginal($protectedField));
                    }
                }

                $category->slug = 'diario';
                $category->channel = 'diary';
                $category->system_type = self::TYPE_DIARY;
                $category->is_system = true;
                $category->allow_polls = false;
                $category->process_type = null;

                return;
            }

            if ($category->isDirty('slug')) {
                $oldSlug = (string) $category->getOriginal('slug');
                $category->previousPermissionResource = self::permissionResourceForSlug($oldSlug);
            }

            $category->channel = 'personal';
            $category->system_type = self::TYPE_STANDARD;
            $category->is_system = false;
        });

        static::created(function (ForumCategory $category): void {
            $category->ensurePermissions();
        });

        static::updated(function (ForumCategory $category): void {
            if ($category->previousPermissionResource) {
                $category->migratePermissionsFrom($category->previousPermissionResource);
                $category->previousPermissionResource = null;
            }
        });

        static::deleting(function (ForumCategory $category): void {
            if ($category->isDiary()) {
                throw new \RuntimeException('Diario es una categoría interna y no se puede eliminar.');
            }

            if ($category->posts()->withTrashed()->exists()) {
                throw new \RuntimeException('Esta categoría todavía contiene hilos. Utiliza la acción de eliminación completa de Filament.');
            }
        });

        static::deleted(function (ForumCategory $category): void {
            $category->deletePermissions();
        });
    }

    public function statuses(): BelongsToMany
    {
        return $this->belongsToMany(
            Status::class,
            'community_forum_category_status',
            'community_forum_category_id',
            'status_id',
        );
    }

    public function posts(): HasMany
    {
        return $this->hasMany(CommunityPost::class, 'forum_category_id');
    }

    public function isDiary(): bool
    {
        if ($this->system_type === self::TYPE_DIARY || $this->slug === 'diario') {
            return true;
        }

        // Durante un update, fill() puede haber cambiado en memoria tanto el
        // slug como el tipo antes de que se ejecute el evento updating. La
        // identidad interna de Diario debe decidirse también con el valor que
        // realmente estaba persistido en base de datos.
        if ($this->exists) {
            return $this->getOriginal('system_type') === self::TYPE_DIARY
                || $this->getOriginal('slug') === 'diario';
        }

        return false;
    }

    public function permissionResource(): ?string
    {
        if ($this->isDiary()) {
            return null;
        }

        return self::permissionResourceForSlug((string) $this->slug);
    }

    public static function permissionResourceForSlug(string $slug): string
    {
        return 'community-forum-' . Str::slug($slug);
    }

    public function ensurePermissions(bool $grantDefaultsWhenCreated = false): void
    {
        $resource = $this->permissionResource();

        if (! $resource) {
            return;
        }

        $createdNames = [];
        $allNames = [];

        foreach (array_keys(self::PERMISSION_ACTIONS) as $action) {
            $name = "{$resource}.{$action}";
            $allNames[] = $name;

            $permission = Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);

            if ($permission->wasRecentlyCreated) {
                $createdNames[] = $name;
            }
        }

        $admin = Role::query()
            ->where('name', 'admin')
            ->where('guard_name', 'web')
            ->first();

        if ($admin) {
            $admin->givePermissionTo($allNames);
        }

        if ($grantDefaultsWhenCreated && $createdNames !== []) {
            $user = Role::query()
                ->where('name', 'user')
                ->where('guard_name', 'web')
                ->first();

            if ($user) {
                $userDefaults = array_values(array_filter(
                    $createdNames,
                    fn (string $name): bool =>
                        str_ends_with($name, '.create')
                        || str_ends_with($name, '.reply')
                        || str_ends_with($name, '.poll')
                ));

                if ($userDefaults !== []) {
                    $user->givePermissionTo($userDefaults);
                }
            }

            $moderator = Role::query()
                ->where('name', 'moderador foro')
                ->where('guard_name', 'web')
                ->first();

            if ($moderator) {
                $moderatorDefaults = array_values(array_filter(
                    $createdNames,
                    fn (string $name): bool =>
                        str_ends_with($name, '.reply')
                        || str_ends_with($name, '.moderate')
                        || str_ends_with($name, '.delete')
                ));

                if ($moderatorDefaults !== []) {
                    $moderator->givePermissionTo($moderatorDefaults);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function deletePermissions(): void
    {
        $resource = $this->permissionResource();

        if (! $resource) {
            return;
        }

        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn(
                'name',
                array_map(
                    fn (string $action): string => "{$resource}.{$action}",
                    array_keys(self::PERMISSION_ACTIONS),
                )
            )
            ->get()
            ->each(fn (Permission $permission) => $permission->delete());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'categoria';
        if (in_array($base, self::RESERVED_SLUGS, true)) {
            $base .= '-foro';
        }

        $slug = $base;
        $suffix = 2;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function migratePermissionsFrom(string $oldResource): void
    {
        $newResource = $this->permissionResource();
        if (! $newResource || $newResource === $oldResource) {
            return;
        }

        foreach (array_keys(self::PERMISSION_ACTIONS) as $action) {
            $oldName = "{$oldResource}.{$action}";
            $newName = "{$newResource}.{$action}";

            $oldPermission = Permission::query()
                ->where('guard_name', 'web')
                ->where('name', $oldName)
                ->first();

            $newPermission = Permission::firstOrCreate([
                'name' => $newName,
                'guard_name' => 'web',
            ]);

            if ($oldPermission) {
                foreach ($oldPermission->roles as $role) {
                    $role->givePermissionTo($newPermission);
                }
                foreach ($oldPermission->users as $user) {
                    $user->givePermissionTo($newPermission);
                }
                $oldPermission->delete();
            }
        }

        $this->ensurePermissions();
    }
}
