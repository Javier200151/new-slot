<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Metopa extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'metopas';

    protected $fillable = [
        'name',
        'description',
        'image',
        'image_large',
        'despag1',
        'despag2',
        'sqa_group_id',
        'imgback',
        'created_by',
        'updated_by',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'metopa_user')
            ->withPivot('assigned_at')
            ->withTimestamps();
    }

    public function sqaGroup()
    {
        return $this->belongsTo(SqaGroup::class, 'sqa_group_id');
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logAll();
    }
}
