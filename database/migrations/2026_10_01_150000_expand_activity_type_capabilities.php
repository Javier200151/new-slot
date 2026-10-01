<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function columns(): array
    {
        return [
            'uses_campaign',
            'uses_days',
            'uses_image',
            'uses_map',
            'uses_period',
            'uses_editor',
            'uses_day_or_night',
            'uses_pbo',
            'uses_briefing',
            'uses_orbat',
            'uses_radio',
            'uses_addons',
            'uses_multiclans',
            'uses_reservations',
            'uses_event_briefing',
            'uses_event_end_date',
        ];
    }

    public function up(): void
    {
        $missing = array_values(array_filter(
            $this->columns(),
            fn (string $column): bool => ! Schema::hasColumn('activity_types', $column),
        ));

        if ($missing === []) {
            return;
        }

        Schema::table('activity_types', function (Blueprint $table) use ($missing): void {
            foreach ($missing as $column) {
                $table->boolean($column)->default(true);
            }
        });
    }

    public function down(): void
    {
        $existing = array_values(array_filter(
            $this->columns(),
            fn (string $column): bool => Schema::hasColumn('activity_types', $column),
        ));

        if ($existing === []) {
            return;
        }

        Schema::table('activity_types', function (Blueprint $table) use ($existing): void {
            $table->dropColumn($existing);
        });
    }
};
