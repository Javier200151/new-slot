<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\SignatureBannerGenerator;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use App\Services\PromoImageGenerator;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasName
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles, LogsActivity, SoftDeletes;

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
        'arma_uid',
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
            'birth_at' => 'date',
            'member_at' => 'date',
        ];
    }

    public function getFilamentName(): string
    {
        return $this->nick ?? $this->email;
    }

    public function canAccessPanel(\Filament\Panel $panel): bool
    {
        return $this->hasRole('admin') || $this->can('filament.access');
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
                'updated_by',
                'deleted_at',
            ])
            ->withTimestamps()
            ->wherePivotNull('deleted_at');
    }

    public function eventComments()
    {
        return $this->hasMany(EventComment::class);
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


    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logAll();
    }
}
