<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class ClipYearContest extends Model
{
    use Auditable;
    protected $fillable = ['year','enabled','allowed_status_ids','tab_text_nominations','tab_text_voting','rules_text','nomination_starts_at','nomination_ends_at','voting_starts_at','voting_ends_at'];
    protected function casts(): array { return [
        'enabled'=>'boolean', 'allowed_status_ids'=>'array', 'nomination_starts_at'=>'datetime', 'nomination_ends_at'=>'datetime',
        'voting_starts_at'=>'datetime', 'voting_ends_at'=>'datetime',
    ]; }
    public function nominations() { return $this->hasMany(ClipYearNomination::class, 'contest_id'); }

    public const DEFAULT_TAB_NOMINATIONS = 'Presenta tu clip del año';
    public const DEFAULT_TAB_VOTING = 'Vota el clip del año';

    public static function defaultRules(): string
    {
        return implode("\n", [
            '1. PARTICIPACIÓN',
            'Solo pueden presentar candidaturas y votar los miembros cuyos estados estén autorizados para esta edición. Las fechas y los estados permitidos se configuran desde Squad ALPHA.',
            '',
            '2. CANDIDATURAS',
            'Cada participante puede presentar un único clip por edición, dentro del plazo establecido. Se aceptan enlaces públicos de clips de Twitch o YouTube Shorts compatibles con la web.',
            '',
            '3. INFORMACIÓN DEL CLIP',
            'La descripción, el evento en el que ocurrió y los miembros protagonistas son opcionales. Si se indican, deben corresponder al contenido del clip.',
            '',
            '4. VALORACIONES',
            'Durante la fase de votación, cada participante puede valorar a los demás candidatos de 1 a 5 calaveras. No se permite votar el candidato propio. Cada valoración puede actualizarse hasta el cierre.',
            '',
            '5. MODERACIÓN',
            'Los administradores pueden retirar candidaturas que incumplan las normas de convivencia de Squad ALPHA, contengan material inapropiado o no puedan reproducirse.',
            '',
            '6. RESULTADOS',
            'Los resultados se ordenan por la media de calaveras recibidas; en caso de empate, se prioriza el número de votos. Los tres primeros puestos permanecen destacados hasta que finalice una nueva edición con resultados.',
        ]);
    }

    public function tabText(): string
    {
        return $this->phase() === 'nominations'
            ? (trim((string) $this->tab_text_nominations) ?: self::DEFAULT_TAB_NOMINATIONS)
            : (trim((string) $this->tab_text_voting) ?: self::DEFAULT_TAB_VOTING);
    }

    public function rulesText(): string
    {
        return $this->rules_text === null ? self::defaultRules() : (string) $this->rules_text;
    }


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
