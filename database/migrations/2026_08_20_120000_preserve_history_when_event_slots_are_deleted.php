<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_slot_history', function (Blueprint $table): void {
            $table->foreignId('event_id')
                ->nullable()
                ->after('event_slot_id')
                ->constrained('events')
                ->cascadeOnDelete();
        });

        DB::table('event_slot_history')
            ->select(['id', 'event_slot_id'])
            ->orderBy('id')
            ->chunkById(500, function ($movements): void {
                $eventIds = DB::table('event_slots')
                    ->whereIn('id', $movements->pluck('event_slot_id'))
                    ->pluck('event_id', 'id');

                foreach ($movements as $movement) {
                    DB::table('event_slot_history')
                        ->where('id', $movement->id)
                        ->update(['event_id' => $eventIds[$movement->event_slot_id] ?? null]);
                }
            });

        Schema::table('event_slot_history', function (Blueprint $table): void {
            $table->dropForeign(['event_slot_id']);
            $table->foreignId('event_slot_id')->nullable()->change();
            $table->foreign('event_slot_id')
                ->references('id')
                ->on('event_slots')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        DB::table('event_slot_history')->whereNull('event_slot_id')->delete();

        Schema::table('event_slot_history', function (Blueprint $table): void {
            $table->dropForeign(['event_slot_id']);
            $table->foreignId('event_slot_id')->nullable(false)->change();
            $table->foreign('event_slot_id')
                ->references('id')
                ->on('event_slots')
                ->cascadeOnDelete();
            $table->dropConstrainedForeignId('event_id');
        });
    }
};
