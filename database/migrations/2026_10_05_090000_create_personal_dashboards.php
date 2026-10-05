<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('personal_dashboards')) {
            Schema::create('personal_dashboards', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('name', 80);
                $table->boolean('is_active')->default(false);
                $table->timestamps();

                $table->unique(['user_id', 'name'], 'pd_user_name_uq');
                $table->index(['user_id', 'is_active'], 'pd_user_active_idx');
            });
        }

        if (! Schema::hasTable('personal_dashboard_widgets')) {
            Schema::create('personal_dashboard_widgets', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('personal_dashboard_id')
                    ->constrained('personal_dashboards')
                    ->cascadeOnDelete();
                $table->string('type', 64);
                $table->unsignedSmallInteger('position')->default(0);
                $table->string('size', 16)->default('2x2');
                $table->json('settings')->nullable();
                $table->timestamps();

                $table->unique(['personal_dashboard_id', 'type'], 'pdw_dash_type_uq');
                $table->index(['personal_dashboard_id', 'position'], 'pdw_dash_pos_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_dashboard_widgets');
        Schema::dropIfExists('personal_dashboards');
    }
};
