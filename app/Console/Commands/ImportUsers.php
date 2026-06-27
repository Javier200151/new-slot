<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PromoImageGenerator;
use Carbon\Carbon;
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

            $promoId = $data['promo_id'] ?? null;

            if (! empty($promoId) && (int) $promoId !== 1) {
                app(PromoImageGenerator::class)->ensure((int) $promoId);
            }

            $birthAt = $this->parseDate($data['birth_at'] ?? null);
            $memberAt = $this->parseDate($data['member_at'] ?? null);

            $user = User::updateOrCreate(
                ['nick' => $data['nick']],
                [
                    'email' => $email,
                    'password' => Hash::make($password),
                    'promo_id' => $promoId,
                    'status_id' => $data['status_id'] ?? null,
                    'birth_at' => $birthAt,
                    'tutor_id' => $data['tutor_id'] ?? null,
                    'tagname' => $data['tagname'] ?? null,
                    'arma_uid' => $data['arma_uid'] ?? null,
                    'discord_id' => $data['discord_id'] ?? null,
                    'steam_id' => $data['steam_id'] ?? null,
                    'member_at' => $memberAt,
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

    private function parseDate(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        return Carbon::createFromFormat('d/m/Y', $date)->format('Y-m-d');
    }
}