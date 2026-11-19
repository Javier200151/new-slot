<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class Platform extends Model
{
    use Auditable;
    public $timestamps = false;

    protected $fillable = [
        'name',
        'image',
        'has_pbo',
    ];

    protected function casts(): array
    {
        return ['has_pbo' => 'boolean'];
    }

    public function isArma3(): bool
    {
        $name = preg_replace('/\s+/', '', mb_strtolower(trim((string) $this->name)));

        return (str_contains($name, 'arma3') || str_contains($name, 'armaiii')) && ! $this->isReforger();
    }

    public function isReforger(): bool
    {
        return str_contains(
            mb_strtolower(trim((string) $this->name)),
            'reforger',
        );
    }
}
