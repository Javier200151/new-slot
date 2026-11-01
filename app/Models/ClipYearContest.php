<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

use Illuminate\Database\Eloquent\Model;

class ClipYearContest extends Model
{
    use Auditable;

    protected $fillable = ['year','enabled','allowed_status_ids','nomination_starts_at','nomination_ends_at','voting_starts_at','voting_ends_at'];
    protected function casts(): array { return [
        'enabled'=>'boolean', 'allowed_status_ids'=>'array', 'nomination_starts_at'=>'datetime', 'nomination_ends_at'=>'datetime',
        'voting_starts_at'=>'datetime', 'voting_ends_at'=>'datetime',
    ]; }
    public function nominations() { return $this->hasMany(ClipYearNomination::class, 'contest_id'); }

    /** Las ediciones anteriores sin configuración conservan ACTIVO por defecto. */
    public function allowedStatusIds(): array
    {
        if ($this->allowed_status_ids === null) {
            $active = Status::query()->where('name', 'ACTIVO')->value('id');
            return $active ? [(int) $active] : [];
        }
        return array_values(array_unique(array_map('intval', (array) $this->allowed_status_ids)));
    }

    public function allowsUser(?User $user): bool
    {
        return $user && in_array((int) $user->status_id, $this->allowedStatusIds(), true);
    }
    public function phase(): string
    {
        if (! $this->enabled) return 'disabled';
        if (now()->betweenIncluded($this->nomination_starts_at, $this->nomination_ends_at)) return 'nominations';
        if (now()->betweenIncluded($this->voting_starts_at, $this->voting_ends_at)) return 'voting';
        return now()->gt($this->voting_ends_at) ? 'results' : 'upcoming';
    }
    /**
     * La portada conserva el último podio con votos hasta que una edición
     * posterior haya finalizado y tenga resultados propios.
     */
    public static function latestWithResults(): ?self
    {
        return self::query()
            ->where('voting_ends_at', '<', now())
            ->whereHas('nominations', fn ($query) => $query
                ->where('is_approved', true)
                ->whereHas('votes')
                ->whereHas('clip'))
            ->orderByDesc('voting_ends_at')
            ->orderByDesc('id')
            ->first();
    }

    public static function current(): ?self
    {
        return self::query()->where('enabled',true)->where('nomination_starts_at','<=',now())->orderByDesc('year')->first();
    }
}
