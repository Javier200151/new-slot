<?php

namespace App\Models;

use App\Support\RecruitmentFormFields;
use Illuminate\Database\Eloquent\Model;

class RecruitmentFormSetting extends Model
{
    protected $fillable = ['fields'];

    protected function casts(): array
    {
        return ['fields' => 'array'];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['fields' => RecruitmentFormFields::defaults()]);
    }
}
