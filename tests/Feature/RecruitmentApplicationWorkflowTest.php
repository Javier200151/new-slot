<?php

namespace Tests\Feature;

use App\Mail\ContactSubmissionAdminMail;
use App\Mail\ContactSubmissionConfirmationMail;
use App\Models\ContactSubmission;
use App\Models\User;
use App\Services\RecruitmentApplicationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RecruitmentApplicationWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
        config()->set('mail.contact_to', 'recruitment@squadalpha.test');
    }

    public function test_public_recruitment_form_saves_optional_discord_and_sends_both_emails(): void
    {
        Mail::fake();

        DB::table('homepage_settings')->insert([
            'recruitment_open' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post(route('public.contact.store'), [
            'nickname' => 'Aspirante',
            'email' => 'aspirante@example.test',
            'message' => 'Quiero entrar en Squad ALPHA.',
            'is_recruitment' => '1',
            'full_name' => 'Aspirante Prueba',
            'birth_date' => now()->subYears(25)->toDateString(),
            'residence' => 'Madrid',
            'phone_whatsapp' => '+34 600 000 000',
            'discord_profile' => 'aspirante.discord',
            'how_heard_us' => 'Por un amigo.',
            'accepted_rules' => '1',
            'is_adult' => '1',
            'accepts_contributions' => '1',
            'has_required_game_content' => '1',
            'tuesday_available' => '1',
            'friday_available' => '1',
            'has_previous_experience' => '0',
            'experience_summary' => 'Sin experiencia previa.',
            'accepted_privacy' => '1',
            'accepted_contact' => '1',
            'website' => '',
        ])->assertRedirect();

        $this->assertDatabaseHas('contact_submissions', [
            'nickname' => 'Aspirante',
            'email' => 'aspirante@example.test',
            'discord_profile' => 'aspirante.discord',
            'is_recruitment' => 1,
            'recruitment_review_status' => ContactSubmission::REVIEW_UNREVIEWED,
        ]);

        Mail::assertSent(ContactSubmissionAdminMail::class, fn (ContactSubmissionAdminMail $mail): bool =>
            $mail->hasTo('recruitment@squadalpha.test'));

        Mail::assertSent(ContactSubmissionConfirmationMail::class, fn (ContactSubmissionConfirmationMail $mail): bool =>
            $mail->hasTo('aspirante@example.test'));
    }

    public function test_approval_matches_user_and_decision_can_be_changed_later(): void
    {
        $this->seedStatusesAndUser(statusId: 1);
        $submission = $this->createRecruitmentSubmission();
        $service = app(RecruitmentApplicationService::class);

        $submission = $service->approve($submission, 10);

        $this->assertSame(ContactSubmission::REVIEW_APPROVED, $submission->recruitment_review_status);
        $this->assertSame(10, (int) $submission->recruitment_matched_user_id);
        $this->assertNull($submission->recruited_at);
        $this->assertSame('Pendiente de acceder al reclutamiento', $submission->recruitmentWorkflowLabel());

        $submission = $service->discard($submission, 10);
        $this->assertSame(ContactSubmission::REVIEW_DISCARDED, $submission->recruitment_review_status);

        $submission = $service->resetDecision($submission);
        $this->assertSame(ContactSubmission::REVIEW_UNREVIEWED, $submission->recruitment_review_status);

        $submission = $service->approve($submission, 10);
        $this->assertSame(ContactSubmission::REVIEW_APPROVED, $submission->recruitment_review_status);
    }

    public function test_approved_application_becomes_recruited_when_matched_user_enters_recruit_status(): void
    {
        $this->seedStatusesAndUser(statusId: 1);
        $service = app(RecruitmentApplicationService::class);
        $submission = $service->approve($this->createRecruitmentSubmission(), 10);

        DB::table('users')->where('id', 10)->update(['status_id' => 2]);
        $user = User::query()->findOrFail(10);

        $service->markRecruitmentStarted($user);

        $submission->refresh();
        $this->assertNotNull($submission->recruited_at);
        $this->assertSame('Reclutado', $submission->recruitmentWorkflowLabel());
    }

    public function test_approved_application_links_when_matching_account_is_created_later(): void
    {
        DB::table('status')->insert([
            ['id' => 1, 'name' => 'ACTIVO', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'RECLUTA', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $submission = $this->createRecruitmentSubmission();
        $submission->update([
            'recruitment_review_status' => ContactSubmission::REVIEW_APPROVED,
            'recruitment_reviewed_at' => now(),
        ]);

        DB::table('users')->insert([
            'id' => 10,
            'nick' => 'Aspirante',
            'email' => 'ASPIRANTE@example.test',
            'password' => 'not-used',
            'status_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(RecruitmentApplicationService::class)
            ->syncUser(User::query()->findOrFail(10));

        $submission->refresh();
        $this->assertSame(10, (int) $submission->recruitment_matched_user_id);
    }

    public function test_recruitment_application_can_be_classified_with_individual_tiers_per_recruiter(): void
    {
        DB::table('status')->insert([
            ['id' => 1, 'name' => 'ACTIVO', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('users')->insert([
            ['id' => 10, 'nick' => 'Hausser', 'email' => 'hausser@example.test', 'password' => 'x', 'status_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 11, 'nick' => 'Ragnar', 'email' => 'ragnar@example.test', 'password' => 'x', 'status_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $submission = $this->createRecruitmentSubmission();
        $service = app(RecruitmentApplicationService::class);

        $submission = $service->setTier(
            $submission,
            ContactSubmission::TIER_1,
            'Muy buen perfil para el proceso.',
            10,
        );

        $submission = $service->setTier(
            $submission,
            ContactSubmission::TIER_3,
            '',
            11,
        );

        $this->assertSame(ContactSubmission::TIER_3, $submission->recruitment_tier);
        $this->assertSame('TIER 3', $submission->recruitmentTierLabel());
        $this->assertSame('danger', $submission->recruitmentTierColor());
        $this->assertNull($submission->recruitment_tier_reason);
        $this->assertCount(2, $submission->recruitmentTierRatingsSummary());

        $this->assertDatabaseHas('recruitment_application_tier_ratings', [
            'contact_submission_id' => $submission->id,
            'user_id' => 10,
            'tier' => ContactSubmission::TIER_1,
            'reason' => 'Muy buen perfil para el proceso.',
        ]);

        $this->assertDatabaseHas('recruitment_application_tier_ratings', [
            'contact_submission_id' => $submission->id,
            'user_id' => 11,
            'tier' => ContactSubmission::TIER_3,
        ]);
    }

    private function createRecruitmentSubmission(): ContactSubmission
    {
        return ContactSubmission::create([
            'nickname' => 'Aspirante',
            'email' => 'aspirante@example.test',
            'message' => 'Solicitud de prueba',
            'is_recruitment' => true,
            'accepted_privacy' => true,
            'accepted_contact' => true,
        ]);
    }

    private function seedStatusesAndUser(int $statusId): void
    {
        DB::table('status')->insert([
            ['id' => 1, 'name' => 'ACTIVO', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'RECLUTA', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('users')->insert([
            'id' => 10,
            'nick' => 'Aspirante',
            'email' => 'aspirante@example.test',
            'password' => 'not-used',
            'status_id' => $statusId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('homepage_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('recruitment_open')->default(false);
            $table->string('contact_email')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('google_photos_url')->nullable();
            $table->string('news_title')->nullable();
            $table->text('news_intro')->nullable();
            $table->string('streams_title')->nullable();
            $table->text('streams_intro')->nullable();
            $table->timestamps();
        });

        Schema::create('status', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('nick');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedBigInteger('status_id')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('contact_submissions', function (Blueprint $table): void {
            $table->id();
            $table->string('nickname', 80)->nullable();
            $table->string('email');
            $table->text('message');
            $table->boolean('is_recruitment')->default(false);
            $table->string('recruitment_review_status', 24)->default(ContactSubmission::REVIEW_UNREVIEWED);
            $table->unsignedTinyInteger('recruitment_tier')->nullable();
            $table->text('recruitment_tier_reason')->nullable();
            $table->unsignedBigInteger('recruitment_tier_marked_by')->nullable();
            $table->timestamp('recruitment_tier_marked_at')->nullable();
            $table->timestamp('recruitment_reviewed_at')->nullable();
            $table->unsignedBigInteger('recruitment_reviewed_by')->nullable();
            $table->unsignedBigInteger('recruitment_interviewer_user_id')->nullable();
            $table->unsignedBigInteger('recruitment_matched_user_id')->nullable();
            $table->timestamp('recruited_at')->nullable();
            $table->string('full_name', 160)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('residence', 160)->nullable();
            $table->string('phone_whatsapp', 40)->nullable();
            $table->string('discord_profile', 160)->nullable();
            $table->text('how_heard_us')->nullable();
            $table->boolean('accepted_rules')->default(false);
            $table->boolean('is_adult')->default(false);
            $table->boolean('accepts_contributions')->default(false);
            $table->boolean('has_required_game_content')->default(false);
            $table->boolean('tuesday_available')->nullable();
            $table->boolean('friday_available')->nullable();
            $table->boolean('has_previous_experience')->default(false);
            $table->text('experience_summary')->nullable();
            $table->boolean('accepted_privacy')->default(false);
            $table->boolean('accepted_contact')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('recruitment_application_tier_ratings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('contact_submission_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedTinyInteger('tier');
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->unique(['contact_submission_id', 'user_id']);
        });
    }
}
