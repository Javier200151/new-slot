<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\Auditable;

class UserMetopa extends Model
{
    use SoftDeletes, Auditable;

    protected $table = 'metopa_user';

    public $incrementing = false;

    protected $fillable = [
        'metopa_id',
        'user_id',
        'assigned_at',
        'created_by',
        'updated_by',
    ];

    public function getKey(): mixed
    {
        $userId = $this->getAttribute('user_id');
        $metopaId = $this->getAttribute('metopa_id');

        if ($userId === null || $metopaId === null) {
            return parent::getKey();
        }

        return "{$userId}-{$metopaId}";
    }
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
/*
    public function getKey(): string
    {
        return $this->metopa_id . '-' . $this->user_id;
    }
*/
    public function getRouteKeyName(): string
    {
        return 'user_id';
    }

    public function metopa(): BelongsTo
    {
        return $this->belongsTo(Metopa::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected function setKeysForSaveQuery($query): Builder
    {
        return $query
            ->where('metopa_id', $this->getAttribute('metopa_id'))
            ->where('user_id', $this->getAttribute('user_id'));
    }
}