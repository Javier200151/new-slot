<?php

namespace Tests\Feature;

use App\Models\RecruitmentReentryReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesEventRegressionSchema;
use Tests\TestCase;

class EventParticipationRegressionTest extends TestCase
{
    use CreatesEventRegressionSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createEventRegressionSchema();
        $this->seedBasicEventRegressionData();
        $this->insertVerifiedUser(10, 'Regression User');
        $this->insertActiveEvent();
    }

    public function test_reservation_is_removed_when_user_takes_a_visible_slot(): void
    {
        $user = User::query()->findOrFail(10);

        $this->actingAs($user)
            ->post(route('events.reservations.store', 1))
            ->assertRedirect(route('events.show', 1) . '#reservas');

        $this->assertDatabaseHas('event_reservations', [
            'event_id' => 1,
            'user_id' => 10,
        ]);

        $this->actingAs($user)
            ->post(route('events.slots.register', [1, 'slot-alpha-1']))
            ->assertRedirect(route('events.show', 1) . '#orbat');

        $this->assertDatabaseMissing('event_reservations', [
            'event_id' => 1,
            'user_id' => 10,
        ]);

        $this->assertDatabaseHas('event_slots', [
            'event_id' => 1,
            'slot_key' => 'slot-alpha-1',
            'user_id' => 10,
            'slot_group' => 'Alpha',
        ]);

        $this->assertDatabaseHas('event_slot_history', [
            'event_id' => 1,
            'user_id' => 10,
            'action' => 'assigned',
            'to_slot_key' => 'slot-alpha-1',
        ]);
    }

    public function test_pending_reserve_retutoring_blocks_every_new_event_participation(): void
    {
        DB::table('recruitment_reentry_reviews')->insert([
            'user_id' => 10,
            'pending_user_id' => 10,
            'previous_period_id' => null,
            'review_type' => RecruitmentReentryReview::TYPE_RESERVE_TO_ACTIVE,
            'detected_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::query()->findOrFail(10);

        $this->actingAs($user)
            ->from(route('events.show', 1))
            ->post(route('events.reservations.store', 1))
            ->assertRedirect(route('events.show', 1))
            ->assertSessionHasErrors('reservation');

        $this->actingAs($user)
            ->from(route('events.show', 1))
            ->post(route('events.slots.register', [1, 'slot-alpha-1']))
            ->assertRedirect(route('events.show', 1))
            ->assertSessionHasErrors('slot');

        $this->assertDatabaseCount('event_reservations', 0);
        $this->assertDatabaseCount('event_slots', 0);
    }

    public function test_active_roulette_room_locks_reservations_and_orbat_registration(): void
    {
        DB::table('community_roulette_rooms')->insert([
            'event_id' => 1,
            'target_slot_key' => 'slot-alpha-1',
            'target_slot_name' => 'Fusilero 1',
            'target_slot_type_id' => 1,
            'target_slot_group' => 'Alpha',
            'target_faction_id' => 1,
            'created_by' => 10,
            'status' => 'active',
            'active_key' => 1,
            'expires_at' => now()->addMinutes(20),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::query()->findOrFail(10);

        $this->getJson(route('events.roulette-lock-state', 1))
            ->assertOk()
            ->assertJsonPath('locked', true)
            ->assertJsonPath('room_id', 1);

        $this->actingAs($user)
            ->from(route('events.show', 1))
            ->post(route('events.reservations.store', 1))
            ->assertRedirect(route('events.show', 1))
            ->assertSessionHasErrors('slot');

        $this->actingAs($user)
            ->from(route('events.show', 1))
            ->post(route('events.slots.register', [1, 'slot-alpha-1']))
            ->assertRedirect(route('events.show', 1))
            ->assertSessionHasErrors('slot');

        $this->assertDatabaseCount('event_reservations', 0);
        $this->assertDatabaseCount('event_slots', 0);
    }

    public function test_hidden_orbat_slot_cannot_be_registered_even_if_the_key_is_known(): void
    {
        $orbat = json_decode((string) DB::table('events')->where('id', 1)->value('orbat'), true);
        $orbat['groups'][0]['slots'][0]['visible'] = false;

        DB::table('events')->where('id', 1)->update([
            'orbat' => json_encode($orbat),
        ]);

        $user = User::query()->findOrFail(10);

        $this->actingAs($user)
            ->from(route('events.show', 1))
            ->post(route('events.slots.register', [1, 'slot-alpha-1']))
            ->assertRedirect(route('events.show', 1))
            ->assertSessionHasErrors('slot');

        $this->assertDatabaseCount('event_slots', 0);
    }
}
