<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class InfrastructureSetting extends Model
{
    protected $fillable = [
        'arma3_academy_host',
        'arma3_academy_query_port',
        'arma3_operations_host',
        'arma3_operations_query_port',
        'reforger_academy_host',
        'reforger_academy_query_port',
        'reforger_operations_host',
        'reforger_operations_query_port',
        'ts3_enabled',
        'tsviewer_server_id',
    ];

    protected function casts(): array
    {
        return [
            'arma3_academy_query_port' => 'integer',
            'arma3_operations_query_port' => 'integer',
            'reforger_academy_query_port' => 'integer',
            'reforger_operations_query_port' => 'integer',
            'ts3_enabled' => 'boolean',
            'tsviewer_server_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (): void {
            Cache::forget('public.infrastructure.status.v2');
        });

        static::deleted(function (): void {
            Cache::forget('public.infrastructure.status.v2');
        });
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'ts3_enabled' => false,
        ]);
    }
}
