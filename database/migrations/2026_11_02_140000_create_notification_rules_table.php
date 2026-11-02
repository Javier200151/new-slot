<?php

use App\Support\NotificationRuleCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_rules', function (Blueprint $table): void {
            $table->string('key', 90)->primary();
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('max_age_value')->nullable();
            $table->string('max_age_unit', 10)->default('months');
            $table->timestamps();
        });

        $now = now();
        foreach (NotificationRuleCatalog::TYPES as $key => $type) {
            DB::table('notification_rules')->insert([
                'key' => $key,
                'enabled' => true,
                'max_age_value' => $type['default'],
                'max_age_unit' => 'months',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_rules');
    }
};
