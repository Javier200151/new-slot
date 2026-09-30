<?php

namespace Database\Seeders;

use App\Models\Status;
use Illuminate\Database\Seeder;

class StatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            'ACTIVO' => '#4ade80',
            'CESADO' => '#f87171',
            'RECLUTA' => '#facc15',
            'BAJA' => '#fb923c',
            'USUARIO' => '#94a3b8',
            'RESERVA' => '#60a5fa',
        ];

        foreach ($statuses as $name => $color) {
            $status = Status::withTrashed()->firstOrNew(['name' => $name]);

            $status->forceFill([
                'color' => $color,
                'is_system' => true,
                'deleted_at' => null,
            ])->save();
        }
    }
}
