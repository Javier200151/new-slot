<?php

use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_raffles', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->text('claim_message')->nullable();
            $table->json('winner_messages')->nullable();
            $table->string('status', 16)->default('draft')->index();
            $table->boolean('show_in_sidebar')->default(true);
            // Un solo sorteo activo. MySQL admite varias claves únicas NULL.
            $table->unsignedTinyInteger('active_key')->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('participants_updated_at')->nullable();
            $table->foreignId('participants_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('community_raffle_prizes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('raffle_id')->constrained('community_raffles')->cascadeOnDelete();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->timestamps();
        });

        Schema::create('community_raffle_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('raffle_id')->constrained('community_raffles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unique(['raffle_id', 'user_id']);
            $table->timestamps();
        });

        Schema::create('community_raffle_draws', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('raffle_id')->constrained('community_raffles')->cascadeOnDelete();
            $table->foreignId('prize_id')->constrained('community_raffle_prizes')->restrictOnDelete();
            $table->foreignId('winner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('winner_nick', 100);
            $table->string('prize_name', 180);
            $table->text('phrase')->nullable();
            $table->string('status', 16)->default('spinning')->index();
            $table->unsignedSmallInteger('winner_index');
            $table->unsignedSmallInteger('candidate_count');
            $table->json('wheel_snapshot');
            $table->timestamp('started_at', 3);
            $table->timestamp('ends_at', 3);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['raffle_id', 'status']);
        });

        $guard = PermissionCatalog::guard();
        $permission = Permission::firstOrCreate(['name' => 'raffles.manage', 'guard_name' => $guard]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => $guard])->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('community_raffle_draws');
        Schema::dropIfExists('community_raffle_entries');
        Schema::dropIfExists('community_raffle_prizes');
        Schema::dropIfExists('community_raffles');
        Permission::where('name', 'raffles.manage')->where('guard_name', PermissionCatalog::guard())->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
