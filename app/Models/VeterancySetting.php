<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class VeterancySetting extends Model
{
    use Auditable;

    protected $fillable = [
        'forum_category_id',
        'bronze_metopa_id',
        'silver_metopa_id',
        'gold_metopa_id',
        'post_body',
    ];

    public static function current(): self
    {
        $setting = static::query()->firstOrCreate([], [
            'post_body' => "[h2]Reconocimiento a nuestros veteranos[/h2]\n\nSquad Alpha reconoce la trayectoria y el tiempo de servicio efectivo de los siguientes miembros:",
        ]);

        if (! Schema::hasTable('community_forum_categories') || ! Schema::hasTable('metopas')) {
            return $setting;
        }

        $defaults = [];

        if (! $setting->forum_category_id) {
            $defaults['forum_category_id'] = ForumCategory::query()
                ->whereRaw('LOWER(title) = ?', ['cuadro de honor'])
                ->value('id');
        }

        foreach ([
            'bronze_metopa_id' => 'VETERANO BRONCE',
            'silver_metopa_id' => 'VETERANO PLATA',
            'gold_metopa_id' => 'VETERANO ORO',
        ] as $field => $name) {
            if (! $setting->{$field}) {
                $defaults[$field] = Metopa::withTrashed()
                    ->whereRaw('UPPER(name) = ?', [$name])
                    ->value('id');
            }
        }

        $defaults = array_filter($defaults, fn ($value): bool => $value !== null);
        if ($defaults !== []) {
            $setting->forceFill($defaults)->saveQuietly();
        }

        return $setting->refresh();
    }

    public function forumCategory()
    {
        return $this->belongsTo(ForumCategory::class, 'forum_category_id');
    }

    public function bronzeMetopa()
    {
        return $this->belongsTo(Metopa::class, 'bronze_metopa_id')->withTrashed();
    }

    public function silverMetopa()
    {
        return $this->belongsTo(Metopa::class, 'silver_metopa_id')->withTrashed();
    }

    public function goldMetopa()
    {
        return $this->belongsTo(Metopa::class, 'gold_metopa_id')->withTrashed();
    }
}
