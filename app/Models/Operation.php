<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Operation extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'operations';

    protected $fillable = [
        'operation_type_id',
        'operation_status_id',
        'campaign_id',
        'date',
        'name',
        'image',
        'description',
        'radio',
        'orbat',
        'ocap',
        'respawn',
        'jip',
        'day_id',
        'pbo',
        'addons',
        'created_by',
        'updated_by',
        'map_id',
        'period_id',
        'editor_id',
        'day_or_night',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
            'ocap' => 'boolean',
            'respawn' => 'boolean',
            'jip' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($operation) {
            if (Auth::check()) {
                $operation->created_by = Auth::id();
                $operation->updated_by = Auth::id();
            }
        });

        static::updating(function ($operation) {
            if (Auth::check()) {
                $operation->updated_by = Auth::id();
            }
        });
    }

    public function operationType()
    {
        return $this->belongsTo(OperationType::class, 'operation_type_id');
    }

    public function operationStatus()
    {
        return $this->belongsTo(OperationStatus::class, 'operation_status_id');
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function day()
    {
        return $this->belongsTo(OperationDay::class, 'day_id');
    }

    public function map()
    {
        return $this->belongsTo(GameMap::class, 'map_id');
    }

    public function period()
    {
        return $this->belongsTo(Period::class, 'period_id');
    }

    public function enemyFactions()
    {
        return $this->belongsToMany(
            Faction::class,
            'enemy_faction_operation',
            'operation_id',
            'faction_id'
        );
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'editor_id');
    }

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
