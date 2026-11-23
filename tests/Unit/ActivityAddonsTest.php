<?php

namespace Tests\Unit;

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Platform;
use App\Support\ActivityAddons;
use Tests\TestCase;

class ActivityAddonsTest extends TestCase
{
    public function test_an_activity_without_addons_returns_an_empty_array(): void
    {
        $activity = new Activity();
        $activity->addons = ['addon_ids' => []];
        $activity->setRelation('activityType', new ActivityType(['uses_addons' => true]));
        $this->assertSame([], ActivityAddons::ids($activity));
        $this->assertSame([], ActivityAddons::names($activity));
    }

    public function test_reforger_and_types_without_addons_do_not_expose_catalog_ids(): void
    {
        $activity = new Activity();
        $activity->addons = ['addon_ids' => [2, 3, 3, 0]];
        $activity->setRelation('platform', new Platform(['name' => 'Arma Reforger']));
        $this->assertSame([], ActivityAddons::ids($activity));
        $this->assertSame([], ActivityAddons::names($activity));

        $activity->setRelation('platform', null);
        $activity->setRelation('activityType', new ActivityType(['uses_addons' => false]));
        $this->assertSame([], ActivityAddons::ids($activity));
    }

    public function test_selected_ids_are_unique_positive_integers(): void
    {
        $activity = new Activity();
        $activity->addons = ['addon_ids' => ['3', '2', 2, 0, -1]];
        $activity->setRelation('platform', null);
        $activity->setRelation('activityType', new ActivityType(['uses_addons' => true]));
        $this->assertSame([3, 2], ActivityAddons::ids($activity));
    }
}
