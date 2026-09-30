<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class Status extends Model
{
    use SoftDeletes, Auditable;

    protected $table = 'status';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Status $status): void {
            // Los estados que ya existían antes de habilitar este panel son
            // parte de la lógica de negocio de NewSlot. Aunque se manipule la
            // petición fuera del formulario, no permitimos renombrarlos ni
            // convertirlos en estados editables.
            if ((bool) $status->getOriginal('is_system')) {
                $status->name = (string) $status->getOriginal('name');
                $status->is_system = true;
            }
        });

        static::deleting(function (Status $status): void {
            if ($status->is_system) {
                throw ValidationException::withMessages([
                    'status' => 'Este es un estado del sistema y no puede eliminarse.',
                ]);
            }

            if ($status->isInUse()) {
                throw ValidationException::withMessages([
                    'status' => 'Este estado está en uso y no puede eliminarse hasta retirar todas sus referencias.',
                ]);
            }
        });
    }

    public function users()
    {
        return $this->hasMany(User::class, 'status_id');
    }

    public function slotTypeStatuses()
    {
        return $this->hasMany(SlotTypeStatus::class, 'status_id');
    }

    public function slotTypes()
    {
        return $this->belongsToMany(
            SlotType::class,
            'slot_types_status',
            'status_id',
            'slot_type_id'
        );
    }

    public function forumCategories()
    {
        return $this->belongsToMany(
            ForumCategory::class,
            'community_forum_category_status',
            'status_id',
            'community_forum_category_id'
        );
    }

    public function finalRecruitmentPeriods()
    {
        return $this->hasMany(RecruitmentPeriod::class, 'final_status_id');
    }

    public function isInUse(): bool
    {
        return $this->users()->withTrashed()->exists()
            || $this->slotTypes()->exists()
            || $this->forumCategories()->exists()
            || $this->finalRecruitmentPeriods()->exists();
    }

    public function deletionBlockReason(): ?string
    {
        if ($this->is_system) {
            return 'Estado protegido del sistema. Puedes cambiar su color, pero no eliminarlo.';
        }

        if ($this->users()->withTrashed()->exists()) {
            return 'No puede eliminarse porque está asignado a uno o más usuarios.';
        }

        if ($this->slotTypes()->exists()) {
            return 'No puede eliminarse porque está permitido en uno o más tipos de slot.';
        }

        if ($this->forumCategories()->exists()) {
            return 'No puede eliminarse porque está asociado a una o más categorías del foro.';
        }

        if ($this->finalRecruitmentPeriods()->exists()) {
            return 'No puede eliminarse porque forma parte del histórico de reclutamiento.';
        }

        return null;
    }
}
