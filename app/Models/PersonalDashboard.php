<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonalDashboard extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function widgets()
    {
        return $this->hasMany(PersonalDashboardWidget::class)
            ->orderBy('position')
            ->orderBy('id');
    }
}
