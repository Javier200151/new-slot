<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class CommunityProcess extends Model
{
    use Auditable, SoftDeletes;

    public const TYPE_CALL = 'convocatoria';
    public const TYPE_PROPOSALS = 'propuestas';
    public const TYPE_CONSULTATION = 'consulta';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_UPCOMING = 'upcoming';
    public const STATUS_DISCUSSION = 'discussion';
    public const STATUS_APPLICATIONS_OPEN = 'applications_open';
    public const STATUS_APPLICATIONS_CLOSED = 'applications_closed';
    public const STATUS_VOTING = 'voting';
    public const STATUS_FINALIZED = 'finalized';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'type',
        'title',
        'description',
        'status',
        'applications_enabled',
        'applications_start_at',
        'applications_end_at',
        'allow_application_edit',
        'allow_application_withdraw',
        'max_winners',
        'eligible_statuses',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'applications_enabled' => 'boolean',
            'allow_application_edit' => 'boolean',
            'allow_application_withdraw' => 'boolean',
            'applications_start_at' => 'datetime',
            'applications_end_at' => 'datetime',
            'eligible_statuses' => 'array',
            'max_winners' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CommunityProcess $process): void {
            if (! $process->created_by && auth()->check()) {
                $process->created_by = auth()->id();
            }

            $process->eligible_statuses ??= ['ACTIVO'];
        });

        static::updated(function (CommunityProcess $process): void {
            if (! $process->wasChanged(['title', 'description'])) {
                return;
            }

            $post = $process->post;
            if (! $post) {
                return;
            }

            $post->forceFill([
                'title' => $process->title,
                'body' => $process->description ?: $post->body,
            ])->saveQuietly();
        });
    }

    public function post()
    {
        return $this->hasOne(CommunityPost::class, 'community_process_id');
    }

    public function applications()
    {
        return $this->hasMany(CommunityProcessApplication::class);
    }

    public function activeApplications()
    {
        return $this->hasMany(CommunityProcessApplication::class)
            ->whereNull('withdrawn_at');
    }

    public function poll()
    {
        return $this->hasOne(CommunityPoll::class, 'community_process_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function applicationsAreOpen(?Carbon $now = null): bool
    {
        if (! $this->applications_enabled) {
            return false;
        }

        $now ??= now();

        return (! $this->applications_start_at || $this->applications_start_at->lte($now))
            && (! $this->applications_end_at || $this->applications_end_at->gte($now))
            && ! in_array($this->status, [
                self::STATUS_DRAFT,
                self::STATUS_CANCELLED,
                self::STATUS_APPLICATIONS_CLOSED,
                self::STATUS_VOTING,
                self::STATUS_FINALIZED,
                self::STATUS_ARCHIVED,
            ], true);
    }

    public function effectiveStatus(): string
    {
        if (in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_CANCELLED,
            self::STATUS_FINALIZED,
            self::STATUS_ARCHIVED,
        ], true)) {
            return $this->status;
        }

        if ($this->poll) {
            if ($this->poll->isOpen()) {
                return self::STATUS_VOTING;
            }

            if ($this->poll->is_published && $this->poll->ends_at?->isPast()) {
                return self::STATUS_FINALIZED;
            }
        }

        if ($this->applications_enabled) {
            if ($this->applications_start_at?->isFuture()) {
                return self::STATUS_UPCOMING;
            }

            if ($this->applicationsAreOpen()) {
                return self::STATUS_APPLICATIONS_OPEN;
            }

            if ($this->applications_end_at?->isPast()) {
                return self::STATUS_APPLICATIONS_CLOSED;
            }
        }

        return self::STATUS_DISCUSSION;
    }

    public function canApply(User $user): bool
    {
        if (! $this->applicationsAreOpen()) {
            return false;
        }

        $eligible = collect($this->eligible_statuses ?: ['ACTIVO'])
            ->map(fn ($status): string => strtoupper((string) $status));

        return $eligible->contains(strtoupper((string) $user->status?->name));
    }

    public static function statusForNewProcess(
        bool $applicationsEnabled,
        mixed $startsAt = null,
        mixed $endsAt = null,
    ): string {
        if (! $applicationsEnabled) {
            return self::STATUS_DISCUSSION;
        }

        $now = now();
        $start = $startsAt ? Carbon::parse($startsAt) : null;
        $end = $endsAt ? Carbon::parse($endsAt) : null;

        if ($start?->gt($now)) {
            return self::STATUS_UPCOMING;
        }

        if ($end?->lt($now)) {
            return self::STATUS_APPLICATIONS_CLOSED;
        }

        return self::STATUS_APPLICATIONS_OPEN;
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Borrador',
            self::STATUS_UPCOMING => 'Próximamente',
            self::STATUS_DISCUSSION => 'En debate',
            self::STATUS_APPLICATIONS_OPEN => 'Postulaciones abiertas',
            self::STATUS_APPLICATIONS_CLOSED => 'Postulaciones cerradas',
            self::STATUS_VOTING => 'En votación',
            self::STATUS_FINALIZED => 'Finalizado',
            self::STATUS_CANCELLED => 'Cancelado',
            self::STATUS_ARCHIVED => 'Archivado',
        ];
    }

    public static function typeOptions(): array
    {
        return [
            self::TYPE_CALL => 'Convocatoria',
            self::TYPE_PROPOSALS => 'Propuesta',
            self::TYPE_CONSULTATION => 'Consulta',
        ];
    }
}
