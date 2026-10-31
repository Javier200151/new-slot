<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HomepageNews extends Model
{
    use Auditable;
    use SoftDeletes;

    protected $table = 'homepage_news';

    protected $fillable = [
        'title', 'excerpt', 'body', 'image', 'external_url',
        'is_published', 'published_at', 'sort_order',
        'category', 'source_type', 'source_key', 'veterancy_groups',
    ];

    public const CATEGORIES = [
        'comunicado' => 'Comunicado',
        'reclutamiento' => 'Reclutamiento',
        'promocion' => 'Promoción',
        'baja' => 'Baja',
        'grupo' => 'Grupos de trabajo',
        'veterania' => 'Veteranías',
        'comunidad' => 'Comunidad',
    ];

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? 'Comunicado';
    }

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'veterancy_groups' => 'array',
        ];
    }
};
