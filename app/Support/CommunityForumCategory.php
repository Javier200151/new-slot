<?php

namespace App\Support;

use App\Models\CommunityPost;
use App\Models\CommunityProcess;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class CommunityForumCategory
{
    public const CANTINA = 'cantina';
    public const DEBATE = 'debate';
    public const CALL = 'convocatoria';
    public const PROPOSAL = 'propuesta';
    public const CONSULTATION = 'consulta';

    public static function personal(): array
    {
        return [
            self::DEBATE => [
                'key' => self::DEBATE,
                'process_type' => null,
                'label' => 'Debates',
                'singular' => 'Debate',
                'icon' => '💬',
                'description' => 'Conversaciones, solicitudes y asuntos generales de la comunidad.',
                'hint' => 'Abre un tema para debatirlo con el resto de miembros.',
                'permission_resource' => 'community-forum-debate',
            ],
            self::CALL => [
                'key' => self::CALL,
                'process_type' => CommunityProcess::TYPE_CALL,
                'label' => 'Convocatorias',
                'singular' => 'Convocatoria',
                'icon' => '📣',
                'description' => 'Postulaciones para cargos, grupos de trabajo, plazas o responsabilidades.',
                'hint' => 'Publica una convocatoria, recibe candidaturas y vincula después una votación.',
                'permission_resource' => 'community-forum-convocatoria',
            ],
            self::PROPOSAL => [
                'key' => self::PROPOSAL,
                'process_type' => CommunityProcess::TYPE_PROPOSALS,
                'label' => 'Propuestas',
                'singular' => 'Propuesta',
                'icon' => '💡',
                'description' => 'Ideas y cambios que se presentan a la comunidad antes de decidir.',
                'hint' => 'Presenta una idea, debátela y añade una votación si procede.',
                'permission_resource' => 'community-forum-propuesta',
            ],
            self::CONSULTATION => [
                'key' => self::CONSULTATION,
                'process_type' => CommunityProcess::TYPE_CONSULTATION,
                'label' => 'Consultas',
                'singular' => 'Consulta',
                'icon' => '🗳️',
                'description' => 'Preguntas para recoger opinión y tomar una decisión cuando sea necesario.',
                'hint' => 'Consulta a la comunidad y vincula una votación al hilo si lo necesitas.',
                'permission_resource' => 'community-forum-consulta',
            ],
        ];
    }

    public static function cantina(): array
    {
        return [
            'key' => self::CANTINA,
            'process_type' => null,
            'label' => 'Cantina',
            'singular' => 'Hilo',
            'icon' => '🍻',
            'description' => 'Temas informales y off-topic de la comunidad.',
            'hint' => 'Abre un hilo informal.',
            'permission_resource' => 'community-forum-cantina',
        ];
    }

    public static function get(string $key): ?array
    {
        if ($key === self::CANTINA) {
            return self::cantina();
        }

        return self::personal()[$key] ?? null;
    }

    public static function keyForPost(CommunityPost $post): string
    {
        if ($post->channel === 'cantina') {
            return self::CANTINA;
        }

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

        if (! $category) {
            return $query->whereRaw('1 = 0');
        }

        if ($key === self::CANTINA) {
            return $query->where('channel', 'cantina');
        }

        $query->where('channel', 'personal');

        if ($category['process_type'] === null) {
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

        $permission = self::permission($key, $action);

        return $permission ? $user->can($permission) : false;
    }
}
