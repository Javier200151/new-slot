<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->clearIncompatibleData();

        if (Schema::hasColumn('armies', 'faction_id')) {
            Schema::table('armies', function (Blueprint $table) {
                $table->dropConstrainedForeignId('faction_id');
            });
        }

        if (! Schema::hasColumn('armies', 'description')) {
            Schema::table('armies', function (Blueprint $table) {
                $table->string('description')->nullable()->after('name');
            });
        }

        if (! Schema::hasColumn('armies', 'image')) {
            Schema::table('armies', function (Blueprint $table) {
                $table->string('image')->nullable()->after('description');
            });
        }

        if (! Schema::hasColumn('factions', 'army_id')) {
            Schema::table('factions', function (Blueprint $table) {
                $table->foreignId('army_id')
                    ->after('id')
                    ->constrained('armies')
                    ->restrictOnDelete();
            });
        }

        Schema::table('factions', function (Blueprint $table) {
            $table->dropForeign(['side_id']);
        });

        Schema::table('factions', function (Blueprint $table) {
            $table->foreignId('side_id')
                ->nullable(false)
                ->change();
        });

        Schema::table('factions', function (Blueprint $table) {
            $table->foreign('side_id')
                ->references('id')
                ->on('sides')
                ->restrictOnDelete();
        });

        $obsoleteFactionColumns = array_values(array_filter(
            ['image', 'description'],
            fn (string $column): bool => Schema::hasColumn('factions', $column),
        ));

        if ($obsoleteFactionColumns !== []) {
            Schema::table('factions', function (Blueprint $table) use ($obsoleteFactionColumns) {
                $table->dropColumn($obsoleteFactionColumns);
            });
        }

        if (Schema::hasColumn('event_slots', 'army_id')) {
            Schema::table('event_slots', function (Blueprint $table) {
                $table->dropConstrainedForeignId('army_id');
            });
        }

        if (! Schema::hasColumn('event_slots', 'faction_id')) {
            Schema::table('event_slots', function (Blueprint $table) {
                $table->foreignId('faction_id')
                    ->after('slot_group')
                    ->constrained('factions')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        $this->clearIncompatibleData();

        Schema::table('event_slots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('faction_id');
            $table->foreignId('army_id')
                ->after('slot_group')
                ->constrained('armies')
                ->restrictOnDelete();
        });

        Schema::table('factions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('army_id');
            $table->dropForeign(['side_id']);
        });

        Schema::table('factions', function (Blueprint $table) {
            $table->foreignId('side_id')
                ->nullable()
                ->change();
        });

        Schema::table('factions', function (Blueprint $table) {
            $table->foreign('side_id')
                ->references('id')
                ->on('sides')
                ->nullOnDelete();
            $table->string('image')->nullable()->after('name');
            $table->string('description')->nullable()->after('image');
        });

        Schema::table('armies', function (Blueprint $table) {
            $table->dropColumn(['description', 'image']);
            $table->foreignId('faction_id')
                ->after('name')
                ->constrained('factions')
                ->restrictOnDelete();
        });
    }

    private function clearIncompatibleData(): void
    {
        DB::table('event_slot_history')->delete();
        DB::table('event_slots')->delete();
        DB::table('enemy_faction_operation')->delete();
        DB::table('armies')->delete();
        DB::table('factions')->delete();

        DB::table('operations')->update([
            'orbat' => json_encode(['groups' => []]),
        ]);

        DB::table('events')->update([
            'orbat' => json_encode(['groups' => []]),
        ]);
    }
};
