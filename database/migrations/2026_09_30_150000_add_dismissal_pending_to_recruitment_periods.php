<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_periods', function (Blueprint $table): void {
            $table->timestamp('dismissal_pending_at')->nullable();
            $table->unsignedBigInteger('dismissal_pending_by')->nullable();

            $table->foreign('dismissal_pending_by', 'recruitment_period_dismissal_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index('dismissal_pending_at', 'recruitment_period_dismissal_idx');
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_periods', function (Blueprint $table): void {
            $table->dropForeign('recruitment_period_dismissal_by_fk');
            $table->dropIndex('recruitment_period_dismissal_idx');
            $table->dropColumn([
                'dismissal_pending_at',
                'dismissal_pending_by',
            ]);
        });
    }
};
