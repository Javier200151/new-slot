<?php

namespace App\Services;

use App\Models\CommunityPoll;
use App\Models\CommunityPollVote;
use App\Models\User;
use Illuminate\Support\Collection;

class CommunityPollViewService
{
    public function forUser(User $user): array
    {
        $polls = CommunityPoll::query()
            ->with([
                'process.post',
                'post',
                'options' => fn ($query) => $query->with(['candidate.status', 'candidate.mainSqaGroup'])->withCount('votes'),
            ])
            ->where('is_published', true)
            ->latest('created_at')
            ->get();

        return $this->decorate($polls, $user);
    }

    public function forPoll(CommunityPoll $poll, User $user): array
    {
        $poll->loadMissing([
            'process.post',
            'post',
            'options' => fn ($query) => $query->with(['candidate.status', 'candidate.mainSqaGroup'])->withCount('votes'),
        ]);

        $data = $this->decorate(collect([$poll]), $user);

        return [
            'poll' => $data['polls']->first(),
            'myVotes' => $data['myVotes']->get($poll->id, collect()),
            'activeMembersCount' => $data['activeMembersCount'],
        ];
    }

    private function decorate(Collection $polls, User $user): array
    {
        $pollIds = $polls->pluck('id');

        $allVotes = CommunityPollVote::query()
            ->with('user:id,nick')
            ->whereIn('community_poll_id', $pollIds)
            ->get()
            ->groupBy('community_poll_id');

        $myVotes = CommunityPollVote::query()
            ->where('user_id', $user->id)
            ->whereIn('community_poll_id', $pollIds)
            ->get()
            ->groupBy('community_poll_id');

        $activeMembersCount = User::query()
            ->whereHas('status', fn ($query) => $query->where('name', 'ACTIVO'))
            ->count();

        foreach ($polls as $poll) {
            $pollVotes = $allVotes->get($poll->id, collect());
            $participantsCount = $pollVotes->pluck('user_id')->unique()->count();

            $poll->setAttribute('participants_count', $participantsCount);
            $poll->setAttribute(
                'abstain_count',
                $pollVotes->where('is_abstain', true)->pluck('user_id')->unique()->count(),
            );
            $poll->setAttribute(
                'quorum_current_percent',
                $activeMembersCount > 0
                    ? (int) round(($participantsCount / $activeMembersCount) * 100)
                    : 0,
            );

            if ($poll->show_voter_names && ! $poll->is_anonymous) {
                foreach ($poll->options as $option) {
                    $option->setAttribute(
                        'voter_names',
                        $pollVotes
                            ->where('community_poll_option_id', $option->id)
                            ->pluck('user.nick')
                            ->filter()
                            ->unique()
                            ->sort()
                            ->values(),
                    );
                }

                $poll->setAttribute(
                    'abstain_voter_names',
                    $pollVotes
                        ->where('is_abstain', true)
                        ->pluck('user.nick')
                        ->filter()
                        ->unique()
                        ->sort()
                        ->values(),
                );
            }
        }

        return compact('polls', 'myVotes', 'activeMembersCount');
    }
}
