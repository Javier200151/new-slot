<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
//use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use App\Models\Status;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasName
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */

    protected $fillable = [
        'nick',
        'email',
        'password',
        'promo_id',
        'tagname',
        'status_id',
        'firma',
        'arma_uid',
        'discord_id',
        'steam_id',
        'member_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
    public function getFilamentName(): string
    {
        return $this->nick ?? $this->email;
    }
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasRole('admin');
    }
    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }
    protected static function booted(): void
    {
        static::created(function ($user) {
            if (! $user->hasAnyRole(['admin', 'user'])) {
                $user->assignRole('user');
            }
        });
    }
}

