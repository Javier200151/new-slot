<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactSubmission extends Model
{
    protected $fillable = [
        'nickname', 'email', 'message', 'is_recruitment', 'accepted_rules', 'is_adult',
        'accepts_contributions', 'has_required_game_content', 'tuesday_available',
        'friday_available', 'has_previous_experience', 'accepted_privacy',
        'accepted_contact', 'ip_address', 'user_agent', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'is_recruitment' => 'boolean',
            'accepted_rules' => 'boolean',
            'is_adult' => 'boolean',
            'accepts_contributions' => 'boolean',
            'has_required_game_content' => 'boolean',
            'tuesday_available' => 'boolean',
            'friday_available' => 'boolean',
            'has_previous_experience' => 'boolean',
            'accepted_privacy' => 'boolean',
            'accepted_contact' => 'boolean',
            'read_at' => 'datetime',
        ];
    }
};
