<?php

namespace Tests\Unit;

use App\Models\ActivityType;
use App\Models\EventMedia;
use App\Models\Platform;
use PHPUnit\Framework\TestCase;

class BlockBConfigurationTest extends TestCase
{
    public function test_new_activity_type_capabilities_are_enabled_by_default_for_legacy_records(): void
    {
        $type = new ActivityType();

        $this->assertTrue($type->usesCampaign());
        $this->assertTrue($type->usesDays());
        $this->assertTrue($type->usesImage());
        $this->assertTrue($type->usesMap());
        $this->assertTrue($type->usesPeriod());
        $this->assertTrue($type->usesEditor());
        $this->assertTrue($type->usesDayOrNight());
        $this->assertTrue($type->usesPbo());
        $this->assertTrue($type->usesBriefing());
        $this->assertTrue($type->usesOrbat());
        $this->assertTrue($type->usesRadio());
        $this->assertTrue($type->usesAddons());
        $this->assertTrue($type->usesMulticlans());
        $this->assertTrue($type->usesReservations());
        $this->assertTrue($type->usesEventBriefing());
        $this->assertTrue($type->usesEventEndDate());
    }

    public function test_activity_type_capabilities_can_be_disabled_individually(): void
    {
        $type = new ActivityType([
            'uses_briefing' => false,
            'uses_addons' => false,
            'uses_reservations' => false,
            'uses_event_briefing' => false,
        ]);

        $this->assertFalse($type->usesBriefing());
        $this->assertFalse($type->usesAddons());
        $this->assertFalse($type->usesReservations());
        $this->assertFalse($type->usesEventBriefing());
        $this->assertTrue($type->usesOrbat());
    }

    public function test_reforger_platform_is_detected_from_platform_name(): void
    {
        $this->assertTrue((new Platform(['name' => 'ArmA Reforger']))->isReforger());
        $this->assertFalse((new Platform(['name' => 'ArmA 3']))->isReforger());
    }

    public function test_event_media_supports_local_photos(): void
    {
        $media = new EventMedia([
            'type' => EventMedia::TYPE_PHOTO,
            'provider' => EventMedia::PROVIDER_LOCAL,
            'file_path' => 'events/10/media/example.webp',
        ]);

        $this->assertTrue($media->isPhoto());
        $this->assertTrue($media->isLocal());
        $this->assertSame('Squad ALPHA', $media->getProviderName());
        $this->assertSame('Foto', $media->getDisplayTitle());
    }
}
