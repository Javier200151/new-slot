<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('community_clips', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->foreignId('protagonist_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
        $names = [
            'community-clips.view','community-clips.create','community-clips.update','community-clips.delete',
            'streamer-vods.create','streamer-vods.delete','streamer-vods.manage','clip-contest.manage',
        ];
        foreach ($names as $name) {
            Permission::findOrCreate($name, 'web');
        }
        if ($admin = Role::query()->where('name', 'admin')->where('guard_name', 'web')->first()) {
            $admin->givePermissionTo($names);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
    public function down(): void
    {
        Schema::table('community_clips', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
            $table->dropConstrainedForeignId('protagonist_user_id');
        });
    }
};
