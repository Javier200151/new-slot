<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'is_protected_admin')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('is_protected_admin')
                    ->default(false)
                    ->after('member_at')
                    ->index('users_protected_admin_idx');
            });
        }

        if (DB::connection()->pretending()) {
            return;
        }

        $configuredEmail = trim((string) config('newslot.protected_admin_email', ''));
        $protectedUserId = null;

        if ($configuredEmail !== '') {
            $protectedUserId = DB::table('users')
                ->whereRaw('LOWER(email) = ?', [Str::lower($configuredEmail)])
                ->value('id');
        }

        // No elegimos otro administrador como fallback: si ADMIN_EMAIL no está
        // disponible durante el despliegue es más seguro no marcar a nadie que
        // proteger silenciosamente una cuenta incorrecta. El hotfix posterior
        // puede reconciliar la marca cuando la configuración sea correcta.
        if ($protectedUserId) {
            DB::table('users')
                ->where('id', $protectedUserId)
                ->update(['is_protected_admin' => true]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'is_protected_admin')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_protected_admin_idx');
            $table->dropColumn('is_protected_admin');
        });
    }
};
