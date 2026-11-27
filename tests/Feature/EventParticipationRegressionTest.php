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

    public function test_twitch_activity_time_requires_streamer_to_be_assigned_to_active_event(): void
    {
        \\Illuminate\\Support\\Facades\\Cache::forget('sqa.twitch.calendar-windows.v1');
        DB::table('events')->where('id', 1)->update([
            'date' => now()->subMinutes(20),
            'end_date' => now()->addMinutes(100),
        ]);
        $streamer = new \\App\\Models\\Streamer();
        $streamer->forceFill(['id' => 9, 'enable' => true, 'user_id' => 10]);
        $filter = app(\\App\\Services\\TwitchArmaCategoryService::class);
        $unrelated = ['game_name' => 'Just Chatting', 'title' => 'Charlando con amigos'];

        // Un evento en horario sin inscripción NO basta para autorizar Twitch.
        $this->assertSame(
            ['allowed' => false, 'reason' => 'not_registered'],
            $filter->liveDecision($unrelated, $streamer),
        );

        DB::table('event_slots')->insert([
            'event_id' => 1, 'slot_key' => 'slot-alpha-1', 'user_id' => 10,
            'name' => 'Fusilero 1', 'slot_type_id' => 1, 'slot_group' => 'Alpha',
            'faction_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertSame(
            ['allowed' => true, 'reason' => null],
            $filter->liveDecision($unrelated, $streamer),
        );

        // Un evento ya finalizado no habilita la excepción de horario.
        DB::table('events')->where('id', 1)->update(['event_status_id' => 2]);
        $this->assertSame(
            ['allowed' => false, 'reason' => 'not_eligible'],
            $filter->liveDecision($unrelated, $streamer),
        );
    }

    public function test_twitch_arma_category_is_still_allowed_without_orbat_assignment(): void
    {
        $filter = app(\\App\\Services\\TwitchArmaCategoryService::class);
        $streamer = new \\App\\Models\\Streamer();
        $streamer->forceFill(['id' => 9, 'enable' => true, 'user_id' => 10]);
        $this->assertSame(
            ['allowed' => true, 'reason' => null],
            $filter->liveDecision(['game_name' => 'Arma 3', 'title' => 'Gameplay'], $streamer),
        );
    }

    public function test_activity_type_can_block_recruit_from_slots_and_reservations(): void
    {
        DB::table('activity_types')->where('id', 1)->update([
            'participation_status_ids' => json_encode([1]),
        ]);
        $recruit = User::query()->findOrFail(10);
        DB::table('users')->where('id', 10)->update(['status_id' => 2]);
        $recruit->refresh();

        $this->actingAs($recruit)
            ->from(route('events.show', 1))
            ->post(route('events.reservations.store', 1))
            ->assertRedirect(route('events.show', 1))
            ->assertSessionHasErrors('reservation');

        $this->actingAs($recruit)
            ->from(route('events.show', 1))
            ->post(route('events.slots.register', [1, 'slot-alpha-1']))
            ->assertRedirect(route('events.show', 1))
            ->assertSessionHasErrors('slot');

        $this->assertDatabaseCount('event_reservations', 0);
        $this->assertDatabaseCount('event_slots', 0);
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
