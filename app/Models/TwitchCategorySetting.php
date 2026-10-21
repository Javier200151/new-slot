<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class TwitchCategorySetting extends Model
{
    protected $fillable = ['allowed_categories'];

    protected function casts(): array
    {
        return ['allowed_categories' => 'array'];
    }

    public static function current(): self
    {
        if (! Schema::hasTable('twitch_category_settings')) {
            return new static(['allowed_categories' => ['Arma 3', 'Arma Reforger']]);
        }

        return static::query()->firstOrCreate(
            ['id' => 1],
            ['allowed_categories' => ['Arma 3', 'Arma Reforger']],
        );
    }
}
