<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonalDashboardWidget extends Model
{
    // Se conservan los nombres por compatibilidad con código previo del bloque M.
    public const SIZE_SQUARE = '1x1';
    public const SIZE_WIDE = '2x2';

    protected $fillable = [
        'personal_dashboard_id',
        'type',
        'position',
        'size',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'settings' => 'array',
        ];
    }

    public function dashboard()
    {
        return $this->belongsTo(PersonalDashboard::class, 'personal_dashboard_id');
    }
}
