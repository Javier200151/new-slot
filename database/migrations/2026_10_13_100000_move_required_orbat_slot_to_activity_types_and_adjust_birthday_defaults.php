<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            if (! Schema::hasColumn('users', 'birthday_visibility_confirmed_at')) {
                Schema::table('users', function (Blueprint $table): void {
                    $table->timestamp('birthday_visibility_confirmed_at')
                        ->nullable()
                        ->after('birthday_public');
                });
            }

            if (Schema::hasColumn('users', 'birthday_public')) {
                Schema::table('users', function (Blueprint $table): void {
                    $table->boolean('birthday_public')
                        ->default(false)
                        ->change();
                });
            }
        }

        if (Schema::hasTable('activity_types') && ! Schema::hasColumn('activity_types', 'required_orbat_slot_type_id')) {
            Schema::table('activity_types', function (Blueprint $table): void {
                $table->unsignedBigInteger('required_orbat_slot_type_id')
                    ->nullable()
                    ->after('uses_orbat');

                $table->foreign('required_orbat_slot_type_id', 'activity_types_required_orbat_slot_fk')
                    ->references('id')
                    ->on('slot_types')
                    ->nullOnDelete();
            });
        }

        if (
            Schema::hasTable('activity_types')
            && Schema::hasTable('slot_types')
            && Schema::hasColumn('activity_types', 'required_orbat_slot_type_id')
        ) {
            $requiredSlotId = null;

            if (Schema::hasColumn('slot_types', 'is_global_command')) {
                $requiredSlotId = DB::table('slot_types')
                    ->where('is_global_command', true)
                    ->orderBy('id')
                    ->value('id');
            }

            $requiredSlotId ??= DB::table('slot_types')
                ->whereRaw('LOWER(name) = ?', ['mando global'])
                ->orderBy('id')
                ->value('id');

            if ($requiredSlotId) {
                DB::table('activity_types')
                    ->where(function ($query): void {
                        $query->whereRaw('UPPER(name) = ?', ['OPERATIVO'])
                            ->orWhereRaw('UPPER(name) = ?', ['OPERATIVOS'])
                            ->orWhereRaw('UPPER(name) = ?', ['OPERACIÓN'])
                            ->orWhereRaw('UPPER(name) = ?', ['OPERACION']);
                    })
                    ->whereNull('required_orbat_slot_type_id')
                    ->update(['required_orbat_slot_type_id' => $requiredSlotId]);
            }
        }

        if (Schema::hasTable('slot_types') && Schema::hasColumn('slot_types', 'is_global_command')) {
            Schema::table('slot_types', function (Blueprint $table): void {
                $table->dropColumn('is_global_command');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('slot_types') && ! Schema::hasColumn('slot_types', 'is_global_command')) {
            Schema::table('slot_types', function (Blueprint $table): void {
                $table->boolean('is_global_command')
                    ->default(false)
                    ->after('description');
            });

            $requiredIds = Schema::hasColumn('activity_types', 'required_orbat_slot_type_id')
                ? DB::table('activity_types')->whereNotNull('required_orbat_slot_type_id')->pluck('required_orbat_slot_type_id')->unique()
                : collect();

            if ($requiredIds->isNotEmpty()) {
                DB::table('slot_types')->whereIn('id', $requiredIds)->update(['is_global_command' => true]);
            }
        }

        if (Schema::hasTable('activity_types') && Schema::hasColumn('activity_types', 'required_orbat_slot_type_id')) {
            Schema::table('activity_types', function (Blueprint $table): void {
                $table->dropForeign('activity_types_required_orbat_slot_fk');
                $table->dropColumn('required_orbat_slot_type_id');
            });
        }

        if (Schema::hasTable('users')) {
            if (Schema::hasColumn('users', 'birthday_public')) {
                Schema::table('users', function (Blueprint $table): void {
                    $table->boolean('birthday_public')
                        ->default(true)
                        ->change();
                });
            }

            if (Schema::hasColumn('users', 'birthday_visibility_confirmed_at')) {
                Schema::table('users', function (Blueprint $table): void {
                    $table->dropColumn('birthday_visibility_confirmed_at');
                });
            }
        }
    }
};
