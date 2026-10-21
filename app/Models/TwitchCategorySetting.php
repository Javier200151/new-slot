<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class TwitchCategorySetting extends Model
{
    protected $fillable = ['allowed_categories', 'title_keywords', 'excluded_vod_ids'];

    protected function casts(): array
    {
        return ['allowed_categories' => 'array', 'title_keywords' => 'array', 'excluded_vod_ids' => 'array'];
    }

    public static function current(): self
    {
        if (! Schema::hasTable('twitch_category_settings')) {
            return new static(['allowed_categories' => ['Arma 3', 'Arma Reforger'], 'title_keywords' => [], 'excluded_vod_ids' => []]);
        }

        return static::query()->firstOrCreate(
            ['id' => 1],
            ['allowed_categories' => ['Arma 3', 'Arma Reforger'], 'title_keywords' => [], 'excluded_vod_ids' => []],
        );
    }
}
