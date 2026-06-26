<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PromoImageGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

class ImportUsers extends Command
{
    protected $signature = 'users:import 
                            {file : Ruta del JSON}
                            {--password=NewSlot123 : Contraseña inicial para todos los usuarios}';

    protected $description = 'Importa usuarios desde un archivo JSON';

    public function handle(): int
    {
        $file = base_path($this->argument('file'));
        $password = $this->option('password');

        if (! File::exists($file)) {
            $this->error("No existe el archivo: {$file}");
            return self::FAILURE;
        }

        $users = json_decode(File::get($file), true);

        if (! is_array($users)) {
            $this->error('El JSON no es válido.');
            return self::FAILURE;
        }

        foreach ($users as $data) {
            if (empty($data['nick'])) {
                $this->warn('Usuario omitido: falta nick.');
                continue;
            }

            $email = $data['email'] ?? strtolower($data['nick']) . '@newslot.local';

            if (! empty($data['promo_id']) && (int) $data['promo_id'] !== 1) {
                app(PromoImageGenerator::class)->ensure((int) $data['promo_id']);
            }

            $user = User::updateOrCreate(
                ['nick' => $data['nick']],
                [
                    'email' => $email,
                    'password' => Hash::make($password),
                    'promo_id' => $data['promo_id'] ?? null,
                    'status_id' => $data['status_id'] ?? null,
                    'tagname' => $data['tagname'] ?? null,
                    'arma_uid' => $data['arma_uid'] ?? null,
                    'discord_id' => $data['discord_id'] ?? null,
                    'steam_id' => $data['steam_id'] ?? null,
                    'member_at' => $data['member_at'] ?? null,
                ]
            );

            if (! $user->hasAnyRole(['admin', 'user'])) {
                $user->assignRole('user');
            }

            $user->applySignatureRules();

            $this->info("✔ {$user->nick}");
        }

        $this->newLine();
        $this->info('Importación de usuarios terminada.');

        return self::SUCCESS;
    }
}