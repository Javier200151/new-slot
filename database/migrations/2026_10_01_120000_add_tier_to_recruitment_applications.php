<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contact_submissions')) {
            return;
        }

        $needsTier = ! Schema::hasColumn('contact_submissions', 'recruitment_tier');
        $needsReason = ! Schema::hasColumn('contact_submissions', 'recruitment_tier_reason');
        $needsMarkedBy = ! Schema::hasColumn('contact_submissions', 'recruitment_tier_marked_by');
        $needsMarkedAt = ! Schema::hasColumn('contact_submissions', 'recruitment_tier_marked_at');

        if (! $needsTier && ! $needsReason && ! $needsMarkedBy && ! $needsMarkedAt) {
            return;
        }

        Schema::table('contact_submissions', function (Blueprint $table) use (
            $needsTier,
            $needsReason,
            $needsMarkedBy,
            $needsMarkedAt,
        ): void {
            if ($needsTier) {
                $table->unsignedTinyInteger('recruitment_tier')
                    ->nullable()
                    ->after('recruitment_review_status');
            }

            if ($needsReason) {
                $table->text('recruitment_tier_reason')
                    ->nullable()
                    ->after('recruitment_tier');
            }

            if ($needsMarkedBy) {
                $table->unsignedBigInteger('recruitment_tier_marked_by')
                    ->nullable()
                    ->after('recruitment_tier_reason');
            }

            if ($needsMarkedAt) {
                $table->timestamp('recruitment_tier_marked_at')
                    ->nullable()
                    ->after('recruitment_tier_marked_by');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('contact_submissions')) {
            return;
        }

        $columns = array_values(array_filter([
            'recruitment_tier',
            'recruitment_tier_reason',
            'recruitment_tier_marked_by',
            'recruitment_tier_marked_at',
        ], fn (string $column): bool => Schema::hasColumn('contact_submissions', $column)));

        if ($columns !== []) {
            Schema::table('contact_submissions', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
