<?php

namespace App\Http\Controllers;

use App\Models\CommunityPoll;
use App\Models\CommunityPollOption;
use App\Models\CommunityPollVote;
use App\Models\CommunityPost;
use App\Services\CommunityPollManager;
use App\Support\CommunityArea;
use App\Support\CommunityForumCategory;
use App\Services\CommunitySubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CommunityPollController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        $this->authorizePolls($request);

        return redirect(
            route('community.forum.home')
        );
    }

    public function storeForPost(
        Request $request,
        CommunityPost $post,
        CommunityPollManager $pollManager,
        CommunitySubscriptionService $subscriptions,
    ): RedirectResponse {
        $this->authorizePersonal($request);
        abort_unless($post->channel === 'personal', 404);
        $categoryKey = CommunityForumCategory::keyForPost($post);
        abort_unless(
            CommunityForumCategory::can($request->user(), $categoryKey, 'poll'),
            403,
            'Tu rol no puede crear votaciones en esta categoría.'
        );
        $this->authorizeManageThread($request, $post);

        $validated = $request->validate([
            'poll_title' => ['nullable', 'string', 'max:180'],
            'poll_description' => ['nullable', 'string', 'max:5000'],
            'poll_options' => ['nullable', 'string', 'max:6000'],
            'poll_selection_mode' => ['nullable', Rule::in([CommunityPoll::MODE_SINGLE, CommunityPoll::MODE_MULTIPLE])],
            'poll_min_choices' => ['nullable', 'integer', 'min:1', 'max:30'],
            'poll_max_choices' => ['nullable', 'integer', 'min:1', 'max:30'],
            'poll_allow_vote_change' => ['nullable', 'boolean'],
            'poll_is_anonymous' => ['nullable', 'boolean'],
            'poll_results_visibility' => ['nullable', Rule::in([
                CommunityPoll::RESULTS_ALWAYS,
                CommunityPoll::RESULTS_AFTER_VOTE,
                CommunityPoll::RESULTS_AFTER_CLOSE,
                CommunityPoll::RESULTS_HIDDEN,
            ])],
            'poll_show_voter_names' => ['nullable', 'boolean'],
            'poll_show_participation' => ['nullable', 'boolean'],
            'poll_allow_abstain' => ['nullable', 'boolean'],
            'poll_randomize_options' => ['nullable', 'boolean'],
            'poll_quorum_percent' => ['nullable', 'integer', 'min:1', 'max:100'],
            'poll_starts_at' => ['nullable', 'date'],
            'poll_ends_at' => ['nullable', 'date', 'after:poll_starts_at'],
            'use_candidates' => ['nullable', 'boolean'],
        ]);

        $pollManager->createForPost(
            $post,
            $request->user(),
            [
                ...$validated,
                'poll_allow_vote_change' => $request->boolean('poll_allow_vote_change'),
                'poll_is_anonymous' => $request->boolean('poll_is_anonymous'),
                'poll_show_voter_names' => $request->boolean('poll_show_voter_names'),
                'poll_show_participation' => $request->boolean('poll_show_participation'),
                'poll_allow_abstain' => $request->boolean('poll_allow_abstain'),
                'poll_randomize_options' => $request->boolean('poll_randomize_options'),
            ],
            $request->boolean('use_candidates'),
        );

        $subscriptions->notifyPost($post, $request->user(), 'poll_created');

        return back()->with('status', 'poll-created');
    }

    public function vote(
        Request $request,
        CommunityPoll $poll,
    ): RedirectResponse {
        $this->authorizePolls($request);

        if (! $poll->isOpen()) {
            return back()->with('status', 'poll-closed');
        }

        $existingVotes = CommunityPollVote::query()
            ->where('community_poll_id', $poll->id)
            ->where('user_id', $request->user()->id)
            ->get();

        if ($existingVotes->isNotEmpty() && ! $poll->allow_vote_change) {
            return back()->with('status', 'vote-locked');
        }

        $abstain = $request->boolean('abstain');
        $optionIds = collect($request->input('option_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($abstain) {
            if (! $poll->allow_abstain) {
                throw ValidationException::withMessages([
                    'abstain' => 'Esta votación no permite abstenerse.',
                ]);
            }

            if ($optionIds->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'option_ids' => 'La abstención no puede combinarse con otras opciones.',
                ]);
            }
        } else {
            $minimum = $poll->isMultipleChoice()
                ? max(1, (int) $poll->min_choices)
                : 1;

            $maximum = $poll->isMultipleChoice()
                ? max($minimum, (int) ($poll->max_choices ?: $poll->options()->count()))
                : 1;

            if ($optionIds->count() < $minimum || $optionIds->count() > $maximum) {
                throw ValidationException::withMessages([
                    'option_ids' => $minimum === $maximum
                        ? "Debes seleccionar exactamente {$minimum} opción(es)."
                        : "Debes seleccionar entre {$minimum} y {$maximum} opciones.",
                ]);
            }

            $validOptionIds = CommunityPollOption::query()
                ->where('community_poll_id', $poll->id)
                ->whereIn('id', $optionIds)
                ->pluck('id');

            if ($validOptionIds->count() !== $optionIds->count()) {
                throw ValidationException::withMessages([
                    'option_ids' => 'Una de las opciones seleccionadas no pertenece a esta votación.',
                ]);
            }
        }

        DB::transaction(function () use ($poll, $request, $abstain, $optionIds): void {
            CommunityPollVote::query()
                ->where('community_poll_id', $poll->id)
                ->where('user_id', $request->user()->id)
                ->delete();

            if ($abstain) {
                CommunityPollVote::create([
                    'community_poll_id' => $poll->id,
                    'community_poll_option_id' => null,
                    'user_id' => $request->user()->id,
                    'is_abstain' => true,
                ]);

                return;
            }

            foreach ($optionIds as $optionId) {
                CommunityPollVote::create([
                    'community_poll_id' => $poll->id,
                    'community_poll_option_id' => $optionId,
                    'user_id' => $request->user()->id,
                    'is_abstain' => false,
                ]);
            }
        });

        return back()->with('status', 'vote-saved');
    }

    private function authorizeManageThread(Request $request, CommunityPost $post): void
    {
        $categoryKey = CommunityForumCategory::keyForPost($post);

        abort_unless(
            $request->user()->hasRole('admin')
            || CommunityForumCategory::can($request->user(), $categoryKey, 'moderate')
            || $post->user_id === $request->user()->id,
            403,
        );
    }

    private function authorizePersonal(Request $request): void
    {
        abort_unless(
            CommunityArea::can($request->user(), CommunityArea::FORUM),
            403,
            'No tienes acceso al Foro.'
        );
    }

    private function authorizePolls(Request $request): void
    {
        abort_unless(
            CommunityArea::can($request->user(), CommunityArea::POLLS),
            403,
            'No tienes acceso a las votaciones.'
        );
    }
}
