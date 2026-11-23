<?php

namespace Tests\Unit;

use App\Models\ActivityType;
use PHPUnit\Framework\TestCase;

class ActivityTypeParticipationStatusesTest extends TestCase
{
    public function test_legacy_types_keep_existing_participation_permissions(): void
    {
        $type = new ActivityType();

        self::assertTrue($type->allowsParticipationStatus(1));
        self::assertTrue($type->allowsParticipationStatus(2));
    }

    public function test_configured_statuses_allow_only_selected_ids(): void
    {
        $type = new ActivityType(['participation_status_ids' => [1, 3]]);

        self::assertTrue($type->allowsParticipationStatus(1));
        self::assertFalse($type->allowsParticipationStatus(2));
        self::assertTrue($type->allowsParticipationStatus(3));
        self::assertFalse($type->allowsParticipationStatus(null));
    }

    public function test_empty_selection_disallows_all_new_participation(): void
    {
        $type = new ActivityType(['participation_status_ids' => []]);

        self::assertFalse($type->allowsParticipationStatus(1));
    }
}
