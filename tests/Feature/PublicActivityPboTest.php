<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityPboExport;
use App\Support\PublicActivityPbo;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicActivityPboTest extends TestCase
{
    private const FILENAME = 'CO@30_OPLOT_SPART_NW.tem_anizay.pbo';

    private const PATH = 'activity-pbos/123e4567-e89b-12d3-a456-426614174000/' . self::FILENAME;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function activity(string|null $path = self::PATH, ?ActivityPboExport $export = null): Activity
    {
        $activity = new Activity();
        $activity->pbo_path = $path;
        $activity->setRelation('pboExport', $export);
        return $activity;
    }

    public function test_a_missing_pbo_does_not_appear_available(): void
    {
        $this->assertSame([
            'pbo_nombre' => null, 'pbo_subido' => false, 'pbo_sincronizado_windows' => false,
            'html_nombre' => null, 'html_generado' => false, 'html_sincronizado_windows' => false,
        ], PublicActivityPbo::forActivity($this->activity(null)));
    }

    public function test_pending_transfer_distinguishes_local_pbo_from_local_html(): void
    {
        Storage::disk('local')->put(self::PATH, 'pbo contents');
        $activity = $this->activity(self::PATH, new ActivityPboExport(['status' => 'pending']));

        $data = PublicActivityPbo::forActivity($activity);
        $this->assertSame(self::FILENAME, $data['pbo_nombre']);
        $this->assertSame('CO@30_OPLOT_SPART_NW.tem_anizay.html', $data['html_nombre']);
        $this->assertTrue($data['pbo_subido']);
        $this->assertFalse($data['html_generado']);
        $this->assertFalse($data['pbo_sincronizado_windows']);
        $this->assertFalse($data['html_sincronizado_windows']);

        Storage::disk('local')->put(dirname(self::PATH) . '/CO@30_OPLOT_SPART_NW.tem_anizay.html', 'html contents');
        $data = PublicActivityPbo::forActivity($activity);
        $this->assertTrue($data['html_generado']);
        $this->assertFalse($data['html_sincronizado_windows']);
    }

    public function test_both_files_only_report_windows_success_after_verified_export(): void
    {
        Storage::disk('local')->put(self::PATH, 'pbo');
        Storage::disk('local')->put(dirname(self::PATH) . '/CO@30_OPLOT_SPART_NW.tem_anizay.html', 'html');
        $export = new ActivityPboExport([
            'status' => 'synced', 'filename' => self::FILENAME,
            'source_path' => self::PATH, 'pbo_sha256' => str_repeat('a', 64),
            'html_sha256' => str_repeat('b', 64),
        ]);
        $activity = $this->activity(self::PATH, $export);
        $data = PublicActivityPbo::forActivity($activity);
        $this->assertTrue($data['pbo_sincronizado_windows']);
        $this->assertTrue($data['html_sincronizado_windows']);

        $export->status = 'replaced';
        $data = PublicActivityPbo::forActivity($activity);
        $this->assertTrue($data['pbo_subido']);
        $this->assertFalse($data['pbo_sincronizado_windows']);
        $this->assertFalse($data['html_sincronizado_windows']);
    }

    public function test_verified_pbo_without_html_is_ready_for_mission_control(): void
    {
        Storage::disk('local')->put(self::PATH, 'pbo-only');
        $export = new ActivityPboExport([
            'status' => 'synced', 'filename' => self::FILENAME,
            'source_path' => self::PATH, 'pbo_sha256' => hash('sha256', 'pbo-only'),
            'html_sha256' => null,
        ]);

        $data = PublicActivityPbo::forActivity($this->activity(self::PATH, $export));
        $this->assertTrue($data['pbo_subido']);
        $this->assertTrue($data['pbo_sincronizado_windows']);
        $this->assertFalse($data['html_generado']);
        $this->assertFalse($data['html_sincronizado_windows']);
    }

    public function test_stale_export_does_not_claim_new_pbo_was_synced(): void
    {
        Storage::disk('local')->put(self::PATH, 'pbo');
        Storage::disk('local')->put(dirname(self::PATH) . '/CO@30_OPLOT_SPART_NW.tem_anizay.html', 'html');
        $export = new ActivityPboExport([
            'status' => 'synced', 'filename' => self::FILENAME,
            'source_path' => 'activity-pbos/aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa/' . self::FILENAME,
            'pbo_sha256' => str_repeat('a', 64), 'html_sha256' => str_repeat('b', 64),
        ]);
        $data = PublicActivityPbo::forActivity($this->activity(self::PATH, $export));
        $this->assertFalse($data['pbo_sincronizado_windows']);
        $this->assertFalse($data['html_sincronizado_windows']);
    }
}
