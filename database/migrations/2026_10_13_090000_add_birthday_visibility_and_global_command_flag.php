<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'birthday_public')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('birthday_public')
                    ->default(true)
                    ->after('birth_at');
            });
        }

        if (Schema::hasTable('slot_types') && ! Schema::hasColumn('slot_types', 'is_global_command')) {
            Schema::table('slot_types', function (Blueprint $table): void {
                $table->boolean('is_global_command')
                    ->default(false)
                    ->after('description');
            });

            DB::table('slot_types')
                ->where(function ($query): void {
                    $query->whereRaw('LOWER(name) = ?', ['mando global'])
                        ->orWhereRaw('LOWER(name) LIKE ?', ['%mando%global%']);
                })
                ->update(['is_global_command' => true]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('slot_types') && Schema::hasColumn('slot_types', 'is_global_command')) {
            Schema::table('slot_types', function (Blueprint $table): void {
                $table->dropColumn('is_global_command');
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'birthday_public')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('birthday_public');
            });
        }
    }
};
