<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EventStatus extends Model
{
    use Auditable;

    protected $table = 'event_status';

    /** Estados utilizados por las reglas de negocio y que no deben renombrarse. */
    public const SYSTEM_NAMES = ['ACTIVO', 'BORRADOR', 'CANCELADO', 'FINALIZADO'];

    protected $fillable = ['name'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (EventStatus $status): void {
            $name = mb_strtoupper(trim((string) $status->name));

            // No permitir crear otro estado equivalente a uno de los del sistema.
            if (in_array($name, self::SYSTEM_NAMES, true)
                && self::query()->whereRaw('UPPER(TRIM(name)) = ?', [$name])->exists()) {
                throw ValidationException::withMessages([
                    'name' => 'Este estado del sistema ya existe.',
                ]);
            }
        });

        static::updating(function (EventStatus $status): void {
            if ((bool) $status->getRawOriginal('is_system')) {
                // Protección también para cambios realizados fuera del formulario.
                $status->name = (string) $status->getRawOriginal('name');
                $status->is_system = true;
            }
        });

        static::deleting(function (EventStatus $status): void {
            $reason = $status->deletionBlockReason();
            if ($reason !== null) {
                throw ValidationException::withMessages(['name' => $reason]);
            }
        });
    }

    public function events()
    {
        return $this->hasMany(Event::class, 'event_status_id');
    }

    public function isInUse(): bool
    {
        // También se comprueban eventos eliminados lógicamente.
        return $this->events()->withTrashed()->exists();
    }

    public function deletionBlockReason(): ?string
    {
        if ($this->is_system) {
            return 'Este estado está protegido porque lo utiliza el sistema de eventos.';
        }

        if ($this->isInUse()) {
            return 'Este estado está asignado a eventos y no puede eliminarse.';
        }

        return null;
    }
}
