<?php

namespace Database\Seeders;

use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        if (! env('ADMIN_PASSWORD')) {
            throw new \Exception('ADMIN_PASSWORD no está definido en el .env');
        }

        $admin = User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
            [
                'nick' => env('ADMIN_NICK', 'admin'),
                'password' => env('ADMIN_PASSWORD'),
                'status_id' => Status::where('name', 'ACTIVO')->value('id'),
            ]
        );

        if (Schema::hasColumn('users', 'is_protected_admin') && ! $admin->is_protected_admin) {
            $admin->forceFill(['is_protected_admin' => true])->saveQuietly();
        }

        $admin->assignRole('admin');
    }
}