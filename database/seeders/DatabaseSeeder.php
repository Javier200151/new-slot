<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            StatusSeeder::class,
            RolesSeeder::class,
            PromoSeeder::class,
            AdminSeeder::class,
            //PlatformSeeder::class,
        ]);
    }
}