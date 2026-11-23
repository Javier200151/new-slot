<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityPboExport;
use App\Services\PersistentCampaignPbo;
use App\Support\PublicActivityPbo;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class PersistentCampaignPboMetadataTest extends TestCase
{
    public function test_shared_export_reports_the_same_verified_pair_for_two_activities(): void
    {
        Storage::fake('local');
        $filename = 'CO@64HC3_Pacific_Shield.Tanoa.pbo';
        $path = 'activity-pbos/123e4567-e89b-12d3-a456-426614174000/' . $filename;
        Storage::disk('local')->put($path, 'mission contents');
        Storage::disk('local')->put(dirname($path) . '/CO@64HC3_Pacific_Shield.Tanoa.html', 'addons contents');

        $export = new ActivityPboExport([
            'pbo_key' => 'campaign:17', 'filename' => $filename,
            'source_path' => $path, 'pbo_sha256' => str_repeat('a', 64),
            'html_sha256' => str_repeat('b', 64), 'status' => 'synced',
        ]);
        foreach ([7, 203] as $id) {
            $activity = new Activity(['pbo_key' => 'campaign:17', 'pbo_path' => $path, 'pbo' => $filename]);
            $activity->setAttribute('id', $id);
            $activity->setRelation('pboExport', $export);
            $result = PublicActivityPbo::forActivity($activity);
            $this->assertSame($filename, $result['pbo_nombre']);
            $this->assertTrue($result['pbo_sincronizado_windows']);
            $this->assertTrue($result['html_sincronizado_windows']);
        }
    }

    public function test_individual_activities_keep_distinct_keys(): void
    {
        $one = new Activity();
        $one->id = 7;
        $two = new Activity();
        $two->id = 203;
        $this->assertSame('activity:7', PersistentCampaignPbo::key($one));
        $this->assertSame('activity:203', PersistentCampaignPbo::key($two));
    }
}
