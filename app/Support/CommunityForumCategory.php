<?php

namespace App\Support;

use App\Models\CommunityPost;
use App\Models\CommunityProcess;
use App\Models\ForumCategory as ForumCategoryModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class CommunityForumCategory
{
    public const CANTINA = 'cantina';
    public const DIARY = 'diario';
    public const DEBATE = 'debate';
    public const CALL = 'convocatoria';
    public const PROPOSAL = 'propuesta';
    public const CONSULTATION = 'consulta';

    public static function personal(): array
    {
        return self::categories()
            ->filter(fn (array $category): bool => $category['channel'] === 'personal')
            ->mapWithKeys(fn (array $category): array => [$category['key'] => $category])
            ->all();
    }

    public static function cantina(): array
    {
        return self::get(self::CANTINA) ?? self::fallback()[self::CANTINA];
    }

    public static function diary(): array
    {
        return self::get(self::DIARY) ?? self::fallback()[self::DIARY];
    }

    public static function landing(): array
    {
        return self::categories()
            ->mapWithKeys(fn (array $category): array => [$category['key'] => $category])
            ->all();
    }

    public static function get(string $key): ?array
    {
        return self::categories()
            ->first(fn (array $category): bool => $category['key'] === $key);
    }

    public static function keyForPost(CommunityPost $post): string
    {
        if (self::databaseReady()) {
            $post->loadMissing('forumCategory');

            if ($post->forumCategory) {
                return $post->forumCategory->slug;
            }
        }

        if ($post->channel === 'cantina') {
            return self::CANTINA;
        }

        $post->loadMissing('process');

        return match ($post->process?->type) {
            CommunityProcess::TYPE_CALL => self::CALL,
            CommunityProcess::TYPE_PROPOSALS => self::PROPOSAL,
            CommunityProcess::TYPE_CONSULTATION => self::CONSULTATION,
            default => self::DEBATE,
        };
    }

    public static function applyToQuery(Builder $query, string $key): Builder
    {
        $category = self::get($key);

        if (! $category || $category['channel'] === 'diary') {
            return $query->whereRaw('1 = 0');
        }

        if (
            self::databaseReady()
            && Schema::hasColumn('community_posts', 'forum_category_id')
            && ! empty($category['id'])
        ) {
            return $query->where('forum_category_id', $category['id']);
        }

        if ($key === self::CANTINA) {
            return $query->where('channel', 'cantina');
        }

        $query->where('channel', 'personal');

        if (($category['process_type'] ?? null) === null) {
            return $query->whereNull('community_process_id');
        }

        return $query->whereHas(
            'process',
            fn (Builder $process): Builder => $process->where('type', $category['process_type'])
        );
    }

    public static function permission(string $key, string $action): ?string
    {
        $resource = self::get($key)['permission_resource'] ?? null;

        return $resource ? "{$resource}.{$action}" : null;
    }

    public static function can(User $user, string $key, string $action): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if (! self::canView($user, $key)) {
            return false;
        }

        $permission = self::permission($key, $action);

        return $permission ? $user->can($permission) : false;
    }

    public static function canView(User $user, string $key): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        $category = self::get($key);

        if (! $category) {
            return false;
        }

        $resource = $category['permission_resource'] ?? null;

        // Los moderadores deben poder entrar en una categoría que administran
        // aunque su estado de usuario no forme parte de la audiencia normal.
        if ($resource && (
            $user->can("{$resource}.moderate")
            || $user->can("{$resource}.delete")
        )) {
            return true;
        }

        if (self::databaseReady() && ! empty($category['id'])) {
            return ForumCategoryModel::query()
                ->whereKey($category['id'])
                ->whereHas(
                    'statuses',
                    fn (Builder $query): Builder => $query->whereKey($user->status_id)
                )
                ->exists();
        }

        return in_array(
            strtoupper((string) $user->status?->name),
            $category['status_names'] ?? [],
            true,
        );
    }

    public static function hasVisibleForumCategory(User $user): bool
    {
        foreach (self::landing() as $key => $category) {
            if (self::canView($user, $key)) {
                return true;
            }
        }

        return false;
    }

    public static function hasVisiblePersonalCategory(User $user): bool
    {
        foreach (self::personal() as $key => $category) {
            if (self::canView($user, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string> permission resource => category title
     */
    public static function permissionResources(): array
    {
        $resources = [];

        if (self::databaseReady()) {
            ForumCategoryModel::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->each(function (ForumCategoryModel $category) use (&$resources): void {
                    $resource = $category->permissionResource();

                    if ($resource) {
                        $resources[$resource] = $category->title;
                    }
                });

            return $resources;
        }

        foreach (self::fallback() as $category) {
            if ($category['permission_resource']) {
                $resources[$category['permission_resource']] = $category['label'];
            }
        }

        return $resources;
    }

    private static function categories(): Collection
    {
        if (! self::databaseReady()) {
            return collect(self::fallback())
                ->sortBy('sort_order')
                ->values();
        }

        return ForumCategoryModel::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (ForumCategoryModel $category): array => self::normalize($category));
    }

    private static function normalize(ForumCategoryModel $category): array
    {
        return [
            'id' => $category->id,
            'key' => $category->slug,
            'process_type' => $category->process_type,
            'label' => $category->title,
            'singular' => $category->singular ?: $category->title,
            'icon' => $category->icon ?: '💬',
            'color' => $category->color ?: '#38bdf8',
            'description' => $category->description ?: '',
            'hint' => $category->hint ?: 'Abre un nuevo hilo en esta categoría.',
            'permission_resource' => $category->permissionResource(),
            'channel' => $category->channel,
            'system_type' => $category->system_type,
            'is_system' => $category->is_system,
            'sort_order' => $category->sort_order,
        ];
    }

    private static function databaseReady(): bool
    {
        try {
            return Schema::hasTable('community_forum_categories');
        } catch (\Throwable) {
            return false;
        }
    }

    private static function fallback(): array
    {
        return [
            self::DEBATE => [
                'id' => null,
                'key' => self::DEBATE,
                'process_type' => null,
                'label' => 'Debates',
                'singular' => 'Debate',
                'icon' => '💬',
                'color' => '#38bdf8',
                'description' => 'Conversaciones, solicitudes y asuntos generales de la comunidad.',
                'hint' => 'Abre un tema para debatirlo con el resto de miembros.',
                'permission_resource' => 'community-forum-debate',
                'channel' => 'personal',
                'system_type' => ForumCategoryModel::TYPE_DEBATE,
                'is_system' => true,
                'sort_order' => 10,
                'status_names' => ['ACTIVO', 'RESERVA'],
            ],
            self::CALL => [
                'id' => null,
                'key' => self::CALL,
                'process_type' => CommunityProcess::TYPE_CALL,
                'label' => 'Convocatorias',
                'singular' => 'Convocatoria',
                'icon' => '📣',
                'color' => '#f43f5e',
                'description' => 'Postulaciones para cargos, grupos de trabajo, plazas o responsabilidades.',
                'hint' => 'Publica una convocatoria, recibe candidaturas y vincula después una votación.',
                'permission_resource' => 'community-forum-convocatoria',
                'channel' => 'personal',
                'system_type' => ForumCategoryModel::TYPE_CALL,
                'is_system' => true,
                'sort_order' => 20,
                'status_names' => ['ACTIVO', 'RESERVA'],
            ],
            self::PROPOSAL => [
                'id' => null,
                'key' => self::PROPOSAL,
                'process_type' => CommunityProcess::TYPE_PROPOSALS,
                'label' => 'Propuestas',
                'singular' => 'Propuesta',
                'icon' => '💡',
                'color' => '#facc15',
                'description' => 'Ideas y cambios que se presentan a la comunidad antes de decidir.',
                'hint' => 'Presenta una idea, debátela y añade una votación si procede.',
                'permission_resource' => 'community-forum-propuesta',
                'channel' => 'personal',
                'system_type' => ForumCategoryModel::TYPE_PROPOSAL,
                'is_system' => true,
                'sort_order' => 30,
                'status_names' => ['ACTIVO', 'RESERVA'],
            ],
            self::CONSULTATION => [
                'id' => null,
                'key' => self::CONSULTATION,
                'process_type' => CommunityProcess::TYPE_CONSULTATION,
                'label' => 'Consultas',
                'singular' => 'Consulta',
                'icon' => '🗳️',
                'color' => '#a78bfa',
                'description' => 'Preguntas para recoger opinión y tomar una decisión cuando sea necesario.',
                'hint' => 'Consulta a la comunidad y vincula una votación al hilo si lo necesitas.',
                'permission_resource' => 'community-forum-consulta',
                'channel' => 'personal',
                'system_type' => ForumCategoryModel::TYPE_CONSULTATION,
                'is_system' => true,
                'sort_order' => 40,
                'status_names' => ['ACTIVO', 'RESERVA'],
            ],
            self::DIARY => [
                'id' => null,
                'key' => self::DIARY,
                'process_type' => null,
                'label' => 'Diarios',
                'singular' => 'Diario',
                'icon' => '📓',
                'color' => '#22c55e',
                'description' => 'Bitácoras personales vinculadas a los eventos en los que ha participado cada jugador.',
                'hint' => 'Consulta los diarios o continúa escribiendo el tuyo.',
                'permission_resource' => null,
                'channel' => 'diary',
                'system_type' => ForumCategoryModel::TYPE_DIARY,
                'is_system' => true,
                'sort_order' => 50,
                'status_names' => ['ACTIVO', 'RESERVA', 'RECLUTA'],
            ],
            self::CANTINA => [
                'id' => null,
                'key' => self::CANTINA,
                'process_type' => null,
                'label' => 'WHISKEY — Enguarrinando',
                'singular' => 'Hilo',
                'icon' => '🥃',
                'color' => '#f97316',
                'description' => 'La zona informal del foro: quedadas, videojuegos, cine, rol y cualquier tema off-topic.',
                'hint' => 'Abre un hilo en WHISKEY.',
                'permission_resource' => 'community-forum-cantina',
                'channel' => 'cantina',
                'system_type' => ForumCategoryModel::TYPE_CANTINA,
                'is_system' => true,
                'sort_order' => 60,
                'status_names' => ['ACTIVO', 'RESERVA', 'RECLUTA'],
            ],
        ];
    }
}
