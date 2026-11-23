<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table): void {
            $table->string('pbo_key', 180)->nullable()->index();
        });
        Schema::table('activity_pbo_exports', function (Blueprint $table): void {
            $table->string('pbo_key', 180)->nullable()->index();
            $table->foreignId('html_activity_id')->nullable()->constrained('activities')->nullOnDelete();
        });

        // An individual activity has its own key; all members of one persistent campaign share one.
        DB::table('activities')->leftJoin('campaign', 'campaign.id', '=', 'activities.campaign_id')
            ->select('activities.id', 'activities.campaign_id', 'campaign.persistent')
            ->orderBy('activities.id')->get()->each(function ($row): void {
                $key = $row->campaign_id && $row->persistent ? 'campaign:' . $row->campaign_id : 'activity:' . $row->id;
                DB::table('activities')->where('id', $row->id)->update(['pbo_key' => $key]);
            });

        $oldExports = DB::table('activity_pbo_exports')->orderBy('id')->get();
        $groups = [];
        foreach ($oldExports as $export) {
            $key = DB::table('activities')->where('id', $export->activity_id)->value('pbo_key');
            if (! $key) { continue; }
            $groups[$key][] = $export;
        }
        foreach ($groups as $key => $rows) {
            usort($rows, static function ($a, $b): int {
                $aScore = $a->status === 'synced' ? 1 : 0;
                $bScore = $b->status === 'synced' ? 1 : 0;
                return ($bScore <=> $aScore) ?: ($b->id <=> $a->id);
            });
            $keep = $rows[0];
            $paths = DB::table('activities')->where('pbo_key', $key)->whereNotNull('pbo_path')
                ->distinct()->pluck('pbo_path');
            $conflict = $paths->count() > 1;
            DB::table('activity_pbo_exports')->where('id', $keep->id)->update([
                'pbo_key' => $key,
                'html_activity_id' => $keep->activity_id,
                'status' => $conflict ? 'error' : $keep->status,
                'last_error' => $conflict
                    ? 'PBO diferentes en la misma campaña. Ejecuta sqa:share-pbos --apply tras revisar el informe.'
                    : $keep->last_error,
            ]);
            foreach (array_slice($rows, 1) as $duplicate) {
                // No Windows deletion here: the migration is database-only.
                DB::table('activity_pbo_exports')->where('id', $duplicate->id)->delete();
            }
        }

        Schema::table('activity_pbo_exports', function (Blueprint $table): void {
            $table->dropForeign(['activity_id']);
            $table->dropUnique('activity_pbo_exports_activity_id_unique');
            $table->dropColumn('activity_id');
        });
        Schema::table('activity_pbo_exports', function (Blueprint $table): void {
            $table->unique('pbo_key');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('La migración de PBO compartidos no es reversible automáticamente: conserva el backup de MySQL.');
    }
};
