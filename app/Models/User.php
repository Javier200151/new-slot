<?php

namespace App\Models;

use App\Services\PromoImageGenerator;
use App\Services\SignatureBannerGenerator;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Concerns\Auditable;
use App\Notifications\VerifyEmailNotification;
use App\Notifications\ResetPasswordNotification;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasName, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Auditable, Notifiable, SoftDeletes;
    protected $fillable = [
        'nick',
        'email',
        'password',
        'promo_id',
        'tagname',
        'status_id',
        'firma',
        'quote',
        'image',
        'discord_id',
        'steam_id',
        'birth_at',
        'tutor_id',
        'member_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
            'birth_at' => 'date',
            'member_at' => 'date',
        ];
    }

    public function getFilamentName(): string
    {
        return $this->nick ?? $this->email;
    }

    public function sendPasswordResetNotification(
        $token
    ): void {
        $this->notify(
            new ResetPasswordNotification($token)
        );
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasRole('admin')
            || $this->can('filament.access')
            || $this->can('event-calendar.view')
            || $this->can('event-calendar.reserve')
            || $this->can('event-calendar.manage');
    }
    
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification());
    }

    protected static function booted(): void
    {
        static::creating(function ($user) {
            if (Auth::check()) {
                $user->created_by = Auth::id();
                $user->updated_by = Auth::id();
            }
        });

        static::updating(function ($user) {
            if (Auth::check()) {
                $user->updated_by = Auth::id();
            }
        });

        static::created(function ($user) {
            if (! $user->hasAnyRole(['admin', 'user'])) {
                $user->assignRole('user');
            }

            $user->applySignatureRules();
        });

        static::updated(function ($user) {
            if ($user->wasChanged('status_id') || $user->wasChanged('nick')) {
                $user->applySignatureRules();
            }
        });
        static::saving(function ($user) {
            if ($user->promo_id) {
                app(PromoImageGenerator::class)->ensure((int) $user->promo_id);
            }
        });
    }

    public function applySignatureRules(): void
    {
        $statusName = $this->status?->name;

        if ($statusName === 'USUARIO') {
            $this->forceFill([
                'firma' => null,
            ])->saveQuietly();

            return;
        }

        if ($statusName === 'RECLUTA') {
            $this->forceFill([
                'promo_id' => null,
                'firma' => $this->getSignatureUrl(),
            ])->saveQuietly();

            return;
        }

        if ($this->promo_id == 1) {
            $this->forceFill([
                'promo_id' => null,
            ])->saveQuietly();
        }

        app(SignatureBannerGenerator::class)->generate($this);
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function promo()
    {
        return $this->belongsTo(Promo::class, 'promo_id');
    }

    public function metopas()
    {
        return $this->belongsToMany(Metopa::class, 'metopa_user')
            ->withPivot('assigned_at')
            ->withTimestamps()
            ->orderByPivot('assigned_at', 'asc');
    }

    public function streamer()
    {
        return $this->hasOne(Streamer::class);
    }

    public function getSignatureUrl(): string
    {
        return route('firmas.show', [
            'nick' => strtolower($this->nick),
        ]);
    }

    public function userMetopas()
    {
        return $this->hasMany(MetopaUser::class, 'user_id', 'id')
            ->orderBy('assigned_at', 'asc');
    }

    public function sqaGroupUsers()
    {
        return $this->hasMany(SqaGroupUser::class);
    }

    public function sqaGroups()
    {
        return $this->belongsToMany(SqaGroup::class, 'sqa_group_users')
            ->withPivot([
                'main',
                'coordinator',
                'updated_by',
                'deleted_at',
            ])
            ->withTimestamps()
            ->wherePivotNull('deleted_at');
    }

    public function mainSqaGroup()
    {
        return $this->hasOneThrough(
            SqaGroup::class,
            SqaGroupUser::class,
            'user_id',
            'id',
            'id',
            'sqa_group_id',
        )->where('sqa_group_users.main', true);
    }

    public function eventMedia()
    {
        return $this->hasMany(
            EventMedia::class
        );
    }

    public function eventComments()
    {
        return $this->hasMany(EventComment::class);
    }

    public function communityPosts()
    {
        return $this->hasMany(CommunityPost::class);
    }

    public function communityPostComments()
    {
        return $this->hasMany(CommunityPostComment::class);
    }

    public function communityProcessApplications()
    {
        return $this->hasMany(CommunityProcessApplication::class);
    }

    public function pupils()
    {
        return $this->hasMany(User::class, 'tutor_id');
    }

    public function tutor()
    {
        return $this->belongsTo(User::class, 'tutor_id');
    }

    // Para que en las listas podamos mostrar el nombre de usuario
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
    public function getFrontendColor(): string
    {
        if (filled($this->mainSqaGroup?->color)) {
            return $this->mainSqaGroup->color;
        }

        return match (strtoupper($this->status?->name ?? '')) {
            'ACTIVO' => '#4ade80',
            'RESERVA' => '#60a5fa',
            'CESADO' => '#f87171',
            'BAJA' => '#fb923c',
            'RECLUTA' => '#facc15',
            'USUARIO' => '#94a3b8',
            default => '#ffffff',
        };
    }
}
