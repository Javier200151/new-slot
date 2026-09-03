<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class HomepageNews extends Model
{
    use Auditable;

    protected $table = 'homepage_news';

    protected $fillable = [
        'title', 'excerpt', 'body', 'image', 'external_url',
        'is_published', 'published_at', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
};
