<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Status;

class StatusSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'ACTIVO',
            'CESADO',
            'RECLUTA',
            'BAJA',
            'USUARIO',
            'RESERVA',
        ] as $status) {
            Status::firstOrCreate([
                'name' => $status,
            ]);
        }
    }
}