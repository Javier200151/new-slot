<?php

namespace Database\Seeders;

use App\Models\Platform;
use Illuminate\Database\Seeder;

class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        Platform::firstOrCreate(['name' => 'ARMA3']);
        Platform::firstOrCreate(['name' => 'REFORGER']);
    }
}