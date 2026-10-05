<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'is_protected_admin')) {
            return;
        }

        $configuredEmail = trim((string) config('newslot.protected_admin_email', ''));

        if ($configuredEmail === '') {
            return;
        }

        $protectedUserId = DB::table('users')
            ->whereRaw('LOWER(email) = ?', [Str::lower($configuredEmail)])
            ->value('id');

        // Si el correo configurado todavía no corresponde a ningún usuario,
        // no tocamos una marca existente. Evitamos dejar la instalación sin
        // administrador protegido por un error tipográfico en el .env.
        if (! $protectedUserId) {
            return;
        }

        DB::transaction(function () use ($protectedUserId): void {
            DB::table('users')
                ->where('is_protected_admin', true)
                ->where('id', '!=', $protectedUserId)
                ->update(['is_protected_admin' => false]);

            DB::table('users')
                ->where('id', $protectedUserId)
                ->update(['is_protected_admin' => true]);
        });
    }

    public function down(): void
    {
        // No revertimos una identidad de administrador protegida: hacerlo
        // podría reactivar una marca incorrecta que esta migración corrigió.
    }
};
