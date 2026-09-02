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
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_enabled' => 'boolean',
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

            $category->channel = $category->channel ?: 'personal';
            $category->system_type = $category->system_type ?: self::TYPE_STANDARD;
        });

        static::created(function (ForumCategory $category): void {
            $category->ensurePermissions();
        });

        static::deleting(function (ForumCategory $category): void {
            if ($category->is_system) {
                throw new \RuntimeException('Las categorías internas del sistema no se pueden eliminar.');
            }

            if ($category->posts()->exists()) {
                throw new \RuntimeException('No se puede eliminar una categoría que ya contiene hilos.');
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

    public function permissionResource(): ?string
    {
        if ($this->system_type === self::TYPE_DIARY) {
            return null;
        }

        return match ($this->system_type) {
            self::TYPE_CANTINA => 'community-forum-cantina',
            self::TYPE_DEBATE => 'community-forum-debate',
            self::TYPE_CALL => 'community-forum-convocatoria',
            self::TYPE_PROPOSAL => 'community-forum-propuesta',
            self::TYPE_CONSULTATION => 'community-forum-consulta',
            default => 'community-forum-' . $this->slug,
        };
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
        $slug = $base;
        $suffix = 2;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }
}
