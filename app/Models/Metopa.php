<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Metopa extends Model
{
    use SoftDeletes;

    protected $table = 'metopas';

    protected $fillable = [
        'name',
        'description',
        'image',
        'image_large',
        'created_by',
        'updated_by',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'metopa_user')
            ->withPivot('assigned_at')
            ->withTimestamps();
    }
    protected static function booted(): void
    {
        static::creating(function ($metopa) {
            if (Auth::check()) {
                $metopa->created_by = Auth::id();
                $metopa->updated_by = Auth::id();
            }
        });

        static::updating(function ($metopa) {
            if (Auth::check()) {
                $metopa->updated_by = Auth::id();
            }
        });
    }
    public function getRouteKeyName(): string
    {
        return 'name';
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
}