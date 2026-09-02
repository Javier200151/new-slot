<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class CommunityPoll extends Model
{
    use Auditable, SoftDeletes;

    public const MODE_SINGLE = 'single';
    public const MODE_MULTIPLE = 'multiple';

    public const RESULTS_ALWAYS = 'always';
    public const RESULTS_AFTER_VOTE = 'after_vote';
    public const RESULTS_AFTER_CLOSE = 'after_close';
    public const RESULTS_HIDDEN = 'hidden';

    protected $fillable = [
        'community_process_id',
        'community_post_id',
        'title',
        'description',
        'is_published',
        'selection_mode',
        'min_choices',
        'max_choices',
        'allow_vote_change',
        'is_anonymous',
        'results_visibility',
        'show_voter_names',
        'show_participation',
        'allow_abstain',
        'randomize_options',
        'quorum_percent',
        'starts_at',
        'ends_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'allow_vote_change' => 'boolean',
            'is_anonymous' => 'boolean',
            'show_voter_names' => 'boolean',
            'show_participation' => 'boolean',
            'allow_abstain' => 'boolean',
            'randomize_options' => 'boolean',
            'min_choices' => 'integer',
            'max_choices' => 'integer',
            'quorum_percent' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CommunityPoll $poll): void {
            if (auth()->check()) {
                $poll->created_by = auth()->id();
            }
        });

        static::saving(function (CommunityPoll $poll): void {
            if ($poll->selection_mode !== self::MODE_MULTIPLE) {
                $poll->selection_mode = self::MODE_SINGLE;
                $poll->min_choices = 1;
                $poll->max_choices = 1;
            } else {
                $poll->min_choices = max(1, (int) $poll->min_choices);

                if ($poll->max_choices !== null) {
                    $poll->max_choices = max(
                        $poll->min_choices,
                        (int) $poll->max_choices,
                    );
                }
            }

            if ($poll->is_anonymous) {
                $poll->show_voter_names = false;
            }
        });
    }

    public function options()
    {
        return $this->hasMany(CommunityPollOption::class)->orderBy('sort_order')->orderBy('id');
    }

    public function votes()
    {
        return $this->hasMany(CommunityPollVote::class);
    }

    public function process()
    {
        return $this->belongsTo(CommunityProcess::class, 'community_process_id');
    }

    public function post()
    {
        return $this->belongsTo(CommunityPost::class, 'community_post_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function isOpen(?Carbon $now = null): bool
    {
        $now ??= now();

        return $this->is_published
            && (! $this->starts_at || $this->starts_at->lte($now))
            && (! $this->ends_at || $this->ends_at->gte($now));
    }

    public function isMultipleChoice(): bool
    {
        return $this->selection_mode === self::MODE_MULTIPLE;
    }

    public function canShowResults(bool $hasVoted = false): bool
    {
        return match ($this->results_visibility) {
            self::RESULTS_ALWAYS => true,
            self::RESULTS_AFTER_VOTE => $hasVoted || ! $this->isOpen(),
            self::RESULTS_AFTER_CLOSE => ! $this->isOpen(),
            default => false,
        };
    }

    public function effectiveMaxChoices(): int
    {
        if (! $this->isMultipleChoice()) {
            return 1;
        }

        return max($this->min_choices, (int) ($this->max_choices ?: $this->options()->count()));
    }
}
