<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('community_diary_entries', 'entry_title')) {
            Schema::table('community_diary_entries', function (Blueprint $table): void {
                $table->string('entry_title', 255)
                    ->nullable()
                    ->after('event_id');
            });
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE community_diary_entries MODIFY event_id BIGINT UNSIGNED NULL');
        } else {
            Schema::table('community_diary_entries', function (Blueprint $table): void {
                $table->unsignedBigInteger('event_id')->nullable()->change();
            });
        }

        DB::table('community_diary_entries')
            ->select(['id', 'event_id', 'content'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                $eventIds = collect($rows)
                    ->pluck('event_id')
                    ->filter()
                    ->unique()
                    ->values();

                $eventNames = $eventIds->isEmpty()
                    ? collect()
                    : DB::table('events')
                        ->whereIn('id', $eventIds)
                        ->pluck('name', 'id');

                foreach ($rows as $row) {
                    $fallback = $eventNames[$row->event_id] ?? null;

                    if (! filled($fallback)) {
                        $content = trim(strip_tags((string) $row->content));
                        $fallback = mb_substr($content, 0, 80);
                    }

                    DB::table('community_diary_entries')
                        ->where('id', $row->id)
                        ->update([
                            'entry_title' => filled($fallback)
                                ? $fallback
                                : 'Entrada de diario',
                        ]);
                }
            });

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE community_diary_entries MODIFY entry_title VARCHAR(255) NOT NULL');
        } else {
            Schema::table('community_diary_entries', function (Blueprint $table): void {
                $table->string('entry_title', 255)->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('community_diary_entries', 'entry_title')) {
            Schema::table('community_diary_entries', function (Blueprint $table): void {
                $table->dropColumn('entry_title');
            });
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE community_diary_entries MODIFY event_id BIGINT UNSIGNED NOT NULL');
        } else {
            Schema::table('community_diary_entries', function (Blueprint $table): void {
                $table->unsignedBigInteger('event_id')->nullable(false)->change();
            });
        }
    }
};
