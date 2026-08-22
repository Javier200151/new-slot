<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class Campaign extends Model
{
    use Auditable;
    protected $table = 'campaign';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'persistent',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'persistent' => 'boolean',
        ];
    }

    public function operations()
    {
        return $this->hasMany(Operation::class);
    }

    public function events()
    {
        return $this->hasManyThrough(
            Event::class,
            Operation::class,
            'campaign_id',
            'operation_id',
        );
    }
}
