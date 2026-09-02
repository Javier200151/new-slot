<?php

namespace App\Services;

use App\Models\CommunityPoll;
use App\Models\CommunityPost;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommunityPollManager
{
    public function createForPost(
        CommunityPost $post,
        User $creator,
        array $data,
        bool $useCandidates = false,
    ): CommunityPoll {
        $post->loadMissing('process.activeApplications.user');

        if ($useCandidates && $post->process?->applicationsAreOpen()) {
            throw ValidationException::withMessages([
                'use_candidates' => 'Cierra primero el plazo de postulaciones antes de convertir las candidaturas en opciones de voto.',
            ]);
        }

        if ($post->process?->applications_enabled && $post->process->applicationsAreOpen()) {
            $applicationsEnd = $post->process->applications_end_at;
            $pollStartsAt = filled($data['poll_starts_at'] ?? null)
                ? Carbon::parse($data['poll_starts_at'])
                : null;

            if (! $applicationsEnd || ! $pollStartsAt || $pollStartsAt->lt($applicationsEnd)) {
                throw ValidationException::withMessages([
                    'poll_starts_at' => 'En una convocatoria con postulaciones abiertas, la votación debe programarse para después del cierre de postulaciones. También puedes crearla más adelante desde el hilo.',
                ]);
            }
        }

        if ($post->poll()->exists()) {
            throw ValidationException::withMessages([
                'poll_enabled' => 'Este hilo ya tiene una votación vinculada.',
            ]);
        }

        $options = $useCandidates
            ? $this->candidateOptions($post)
            : $this->textOptions((string) ($data['poll_options'] ?? ''));

        if ($options->count() < 2) {
            throw ValidationException::withMessages([
                'poll_options' => $useCandidates
                    ? 'Se necesitan al menos dos postulaciones activas para crear la votación con candidatos.'
                    : 'Añade al menos dos opciones, una por línea.',
            ]);
        }

        return DB::transaction(function () use ($post, $creator, $data, $options): CommunityPoll {
            $mode = ($data['poll_selection_mode'] ?? CommunityPoll::MODE_SINGLE) === CommunityPoll::MODE_MULTIPLE
                ? CommunityPoll::MODE_MULTIPLE
                : CommunityPoll::MODE_SINGLE;

            $minChoices = $mode === CommunityPoll::MODE_MULTIPLE
                ? max(1, (int) ($data['poll_min_choices'] ?? 1))
                : 1;

            $maxChoices = $mode === CommunityPoll::MODE_MULTIPLE
                ? max($minChoices, min($options->count(), (int) ($data['poll_max_choices'] ?? $options->count())))
                : 1;

            $poll = CommunityPoll::create([
                'community_post_id' => $post->id,
                'community_process_id' => $post->community_process_id,
                'title' => trim((string) ($data['poll_title'] ?? '')) ?: $post->title,
                'description' => trim((string) ($data['poll_description'] ?? '')) ?: null,
                'is_published' => true,
                'selection_mode' => $mode,
                'min_choices' => $minChoices,
                'max_choices' => $maxChoices,
                'allow_vote_change' => (bool) ($data['poll_allow_vote_change'] ?? true),
                'is_anonymous' => (bool) ($data['poll_is_anonymous'] ?? false),
                'results_visibility' => $data['poll_results_visibility'] ?? CommunityPoll::RESULTS_ALWAYS,
                'show_voter_names' => (bool) ($data['poll_show_voter_names'] ?? false),
                'show_participation' => (bool) ($data['poll_show_participation'] ?? true),
                'allow_abstain' => (bool) ($data['poll_allow_abstain'] ?? false),
                'randomize_options' => (bool) ($data['poll_randomize_options'] ?? false),
                'quorum_percent' => filled($data['poll_quorum_percent'] ?? null)
                    ? (int) $data['poll_quorum_percent']
                    : null,
                'starts_at' => $data['poll_starts_at'] ?? null,
                'ends_at' => $data['poll_ends_at'] ?? null,
                'created_by' => $creator->id,
            ]);

            foreach ($options->values() as $index => $option) {
                $poll->options()->create([
                    'candidate_user_id' => $option['candidate_user_id'] ?? null,
                    'label' => $option['label'],
                    'sort_order' => ($index + 1) * 10,
                ]);
            }

            $post->touch();

            return $poll->load('options');
        });
    }

    private function textOptions(string $raw): Collection
    {
        return collect(preg_split('/\R/u', $raw) ?: [])
            ->map(fn (string $option): string => trim($option))
            ->filter()
            ->unique(fn (string $option): string => mb_strtolower($option))
            ->take(30)
            ->map(fn (string $option): array => [
                'label' => mb_substr($option, 0, 180),
                'candidate_user_id' => null,
            ])
            ->values();
    }

    private function candidateOptions(CommunityPost $post): Collection
    {
        if (! $post->process) {
            return collect();
        }

        return $post->process->activeApplications
            ->filter(fn ($application): bool => (bool) $application->user)
            ->unique('user_id')
            ->map(fn ($application): array => [
                'label' => $application->user->nick,
                'candidate_user_id' => $application->user_id,
            ])
            ->values();
    }
}
