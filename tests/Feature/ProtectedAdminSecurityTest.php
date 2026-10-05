<?php

namespace Tests\Feature;

use App\Models\SqaGroupUser;
use App\Models\User;
use App\Services\ProtectedAdminGuard;
use App\Services\UserMetopaAssignmentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProtectedAdminSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('nick');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('status_id')->nullable();
            $table->unsignedBigInteger('promo_id')->nullable();
            $table->unsignedBigInteger('tutor_id')->nullable();
            $table->date('member_at')->nullable();
            $table->boolean('is_protected_admin')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sqa_group_users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('sqa_group_id');
            $table->unsignedBigInteger('user_id');
            $table->boolean('main')->default(false);
            $table->boolean('coordinator')->default(false);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('users')->insert([
            [
                'id' => 1,
                'nick' => 'Principal',
                'email' => 'principal@example.test',
                'is_protected_admin' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'nick' => 'OtroAdmin',
                'email' => 'otro@example.test',
                'is_protected_admin' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function test_other_authenticated_user_cannot_modify_or_delete_protected_admin(): void
    {
        $protected = User::query()->findOrFail(1);
        $other = User::query()->findOrFail(2);

        $this->actingAs($other);

        $this->expectException(AuthorizationException::class);

        $protected->forceFill(['email' => 'changed@example.test'])->save();
    }

    public function test_protected_admin_can_modify_own_account_and_protection_persists(): void
    {
        $protected = User::query()->findOrFail(1);

        $this->actingAs($protected);

        $protected->forceFill(['email' => 'nuevo@example.test'])->save();

        $protected->refresh();

        $this->assertSame('nuevo@example.test', $protected->email);
        $this->assertTrue($protected->isProtectedAdmin());
        $this->assertTrue((bool) $protected->is_protected_admin);
    }

    public function test_relations_and_metopa_service_cannot_modify_protected_admin_from_another_account(): void
    {
        $other = User::query()->findOrFail(2);
        $this->actingAs($other);

        try {
            SqaGroupUser::query()->create([
                'sqa_group_id' => 10,
                'user_id' => 1,
                'main' => true,
            ]);

            $this->fail('La relación de grupo del administrador protegido no debería poder modificarse.');
        } catch (AuthorizationException) {
            $this->assertDatabaseMissing('sqa_group_users', [
                'user_id' => 1,
                'sqa_group_id' => 10,
            ]);
        }

        $this->expectException(AuthorizationException::class);

        app(UserMetopaAssignmentService::class)->assign(
            userId: 1,
            metopaId: 99,
            assignedAt: now(),
        );
    }

    public function test_guard_allows_normal_users_to_be_modified_by_admins(): void
    {
        $protected = User::query()->findOrFail(1);
        $normal = User::query()->findOrFail(2);
        $guard = app(ProtectedAdminGuard::class);

        $this->assertTrue($guard->canModify($normal, $protected));
        $this->assertFalse($guard->canModify($protected, $normal));
        $this->assertTrue($guard->canModify($protected, $protected));
    }
}
