<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class CommunityRouletteRoom extends Model
{
    use Auditable;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_SPINNING = 'spinning';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'event_id',
        'target_slot_key',
        'target_slot_name',
        'target_slot_type_id',
        'target_slot_group',
        'target_faction_id',
        'created_by',
        'status',
        'active_key',
        'expires_at',
        'spin_started_at',
        'spin_ends_at',
        'spin_duration_ms',
        'winning_ticket_index',
        'final_rotation',
        'winner_user_id',
        'winner_was_viewing',
        'winner_phrase_id',
        'winner_phrase_text',
        'failure_reason',
        'completed_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'spin_started_at' => 'datetime',
            'spin_ends_at' => 'datetime',
            'spin_duration_ms' => 'integer',
            'winning_ticket_index' => 'integer',
            'final_rotation' => 'float',
            'winner_was_viewing' => 'boolean',
            'completed_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function targetSlotType()
    {
        return $this->belongsTo(SlotType::class, 'target_slot_type_id');
    }

    public function targetFaction()
    {
        return $this->belongsTo(Faction::class, 'target_faction_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_user_id')->withTrashed();
    }

    public function winnerPhrase()
    {
        return $this->belongsTo(CommunityRoulettePhrase::class, 'winner_phrase_id');
    }

    public function previousEvents()
    {
        return $this->hasMany(CommunityRoulettePreviousEvent::class, 'room_id')
            ->orderBy('position');
    }

    public function rules()
    {
        return $this->hasMany(CommunityRouletteSlotTypeRule::class, 'room_id')
            ->orderBy('slot_type_name_snapshot');
    }

    public function candidates()
    {
        return $this->hasMany(CommunityRouletteCandidate::class, 'room_id')
            ->orderBy('nick_snapshot');
    }

    public function viewers()
    {
        return $this->hasMany(CommunityRouletteViewer::class, 'room_id');
    }

    public function locksEventRegistration(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_SPINNING], true)
            && $this->active_key !== null;
    }

    public function canBeConfigured(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->active_key !== null
            && $this->expires_at?->isFuture();
    }

    public function isRecent(): bool
    {
        return $this->expires_at?->isFuture() ?? false;
    }
}
