<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

use Illuminate\Database\Eloquent\Model;

class PublicFooterSetting extends Model
{
    use Auditable;

    protected $fillable = ['items'];

    protected function casts(): array
    {
        return ['items' => 'array'];
    }
}
