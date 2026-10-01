<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSION = 'recruitment-applications.manage';

    public function up(): void
    {
        if (Schema::hasTable('contact_submissions')) {
            $needsDiscord = ! Schema::hasColumn('contact_submissions', 'discord_profile');
            $needsStatus = ! Schema::hasColumn('contact_submissions', 'recruitment_review_status');
            $needsReviewedAt = ! Schema::hasColumn('contact_submissions', 'recruitment_reviewed_at');
            $needsReviewedBy = ! Schema::hasColumn('contact_submissions', 'recruitment_reviewed_by');
            $needsInterviewer = ! Schema::hasColumn('contact_submissions', 'recruitment_interviewer_user_id');
            $needsMatchedUser = ! Schema::hasColumn('contact_submissions', 'recruitment_matched_user_id');
            $needsRecruitedAt = ! Schema::hasColumn('contact_submissions', 'recruited_at');

            if (
                $needsDiscord
                || $needsStatus
                || $needsReviewedAt
                || $needsReviewedBy
                || $needsInterviewer
                || $needsMatchedUser
                || $needsRecruitedAt
            ) {
                Schema::table('contact_submissions', function (Blueprint $table) use (
                    $needsDiscord,
                    $needsStatus,
                    $needsReviewedAt,
                    $needsReviewedBy,
                    $needsInterviewer,
                    $needsMatchedUser,
                    $needsRecruitedAt,
                ): void {
                    if ($needsDiscord) {
                        $table->string('discord_profile', 160)->nullable()->after('phone_whatsapp');
                    }

                    if ($needsStatus) {
                        $table->string('recruitment_review_status', 24)
                            ->default('unreviewed')
                            ->after('is_recruitment')
                            ->index('cs_review_status_idx');
                    }

                    if ($needsReviewedAt) {
                        $table->timestamp('recruitment_reviewed_at')
                            ->nullable()
                            ->after('recruitment_review_status')
                            ->index('cs_reviewed_at_idx');
                    }

                    if ($needsReviewedBy) {
                        $table->unsignedBigInteger('recruitment_reviewed_by')
                            ->nullable()
                            ->after('recruitment_reviewed_at')
                            ->index('cs_reviewed_by_idx');
                    }

                    if ($needsInterviewer) {
                        $table->unsignedBigInteger('recruitment_interviewer_user_id')
                            ->nullable()
                            ->after('recruitment_reviewed_by')
                            ->index('cs_interviewer_idx');
                    }

                    if ($needsMatchedUser) {
                        $table->unsignedBigInteger('recruitment_matched_user_id')
                            ->nullable()
                            ->after('recruitment_interviewer_user_id')
                            ->index('cs_matched_user_idx');
                    }

                    if ($needsRecruitedAt) {
                        $table->timestamp('recruited_at')
                            ->nullable()
                            ->after('recruitment_matched_user_id')
                            ->index('cs_recruited_at_idx');
                    }
                });
            }
        }

        if (! Schema::hasTable('recruitment_application_comments')) {
            Schema::create('recruitment_application_comments', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('contact_submission_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->text('content');
                $table->timestamps();

                $table->foreign('contact_submission_id', 'rac_submission_fk')
                    ->references('id')
                    ->on('contact_submissions')
                    ->cascadeOnDelete();

                $table->foreign('user_id', 'rac_user_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->index(
                    ['contact_submission_id', 'created_at'],
                    'rac_submission_date_idx'
                );
            });
        }

        $this->ensurePermissionExists();
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_application_comments');

        if (Schema::hasTable('contact_submissions')) {
            $columns = array_values(array_filter([
                'discord_profile',
                'recruitment_review_status',
                'recruitment_reviewed_at',
                'recruitment_reviewed_by',
                'recruitment_interviewer_user_id',
                'recruitment_matched_user_id',
                'recruited_at',
            ], fn (string $column): bool => Schema::hasColumn('contact_submissions', $column)));

            if ($columns !== []) {
                Schema::table('contact_submissions', function (Blueprint $table) use ($columns): void {
                    $table->dropColumn($columns);
                });
            }
        }

        $tableNames = config('permission.table_names');
        $permissionsTable = $tableNames['permissions'] ?? 'permissions';
        $rolePermissionTable = $tableNames['role_has_permissions'] ?? 'role_has_permissions';

        if (Schema::hasTable($permissionsTable)) {
            $permissionId = DB::table($permissionsTable)
                ->where('name', self::PERMISSION)
                ->where('guard_name', 'web')
                ->value('id');

            if ($permissionId !== null && Schema::hasTable($rolePermissionTable)) {
                DB::table($rolePermissionTable)
                    ->where('permission_id', $permissionId)
                    ->delete();
            }

            DB::table($permissionsTable)
                ->where('name', self::PERMISSION)
                ->where('guard_name', 'web')
                ->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function ensurePermissionExists(): void
    {
        $tableNames = config('permission.table_names');
        $permissionsTable = $tableNames['permissions'] ?? 'permissions';
        $rolesTable = $tableNames['roles'] ?? 'roles';
        $rolePermissionTable = $tableNames['role_has_permissions'] ?? 'role_has_permissions';

        if (! Schema::hasTable($permissionsTable)) {
            return;
        }

        DB::table($permissionsTable)->updateOrInsert(
            [
                'name' => self::PERMISSION,
                'guard_name' => 'web',
            ],
            [
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        if (Schema::hasTable($rolesTable) && Schema::hasTable($rolePermissionTable)) {
            $permissionId = DB::table($permissionsTable)
                ->where('name', self::PERMISSION)
                ->where('guard_name', 'web')
                ->value('id');

            $adminRoleId = DB::table($rolesTable)
                ->where('name', 'admin')
                ->where('guard_name', 'web')
                ->value('id');

            if ($permissionId !== null && $adminRoleId !== null) {
                DB::table($rolePermissionTable)->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $adminRoleId,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
