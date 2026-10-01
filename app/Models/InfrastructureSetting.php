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
        'ts3_host',
        'ts3_query_port',
        'ts3_virtual_server_id',
        'ts3_query_user',
        'ts3_query_password',
    ];

    protected function casts(): array
    {
        return [
            'arma3_academy_query_port' => 'integer',
            'arma3_operations_query_port' => 'integer',
            'reforger_academy_query_port' => 'integer',
            'reforger_operations_query_port' => 'integer',
            'ts3_enabled' => 'boolean',
            'ts3_query_port' => 'integer',
            'ts3_virtual_server_id' => 'integer',
            'ts3_query_password' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (): void {
            Cache::forget('public.infrastructure.status.v1');
        });

        static::deleted(function (): void {
            Cache::forget('public.infrastructure.status.v1');
        });
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'ts3_enabled' => false,
            'ts3_query_port' => 10011,
            'ts3_virtual_server_id' => 1,
        ]);
    }
}
