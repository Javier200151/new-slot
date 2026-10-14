<?php

namespace Tests\Feature;

use App\Models\Metopa;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserMetopaVisibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('nick');
            $table->string('email');
            $table->string('password');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('metopas', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('image');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('metopa_user', function (Blueprint $table): void {
            $table->unsignedBigInteger('metopa_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('assigned_at');
            $table->timestamps();
            $table->softDeletes();
            $table->primary(['metopa_id', 'user_id']);
        });

        DB::table('users')->insert([
            'id' => 10,
            'nick' => 'Veterano',
            'email' => 'veterano@example.test',
            'password' => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([1 => 'Veterano Bronce', 2 => 'Veterano Plata', 3 => 'Veterano Oro', 4 => 'GRA'] as $id => $name) {
            DB::table('metopas')->insert([
                'id' => $id,
                'name' => $name,
                'image' => "metopas/{$id}.png",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('metopa_user')->insert([
                'user_id' => 10,
                'metopa_id' => $id,
                'assigned_at' => now()->subDays(4 - $id),
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => $id < 3 ? now() : null,
            ]);
        }
    }

    public function test_public_relationship_only_returns_active_metopas_without_deleting_history(): void
    {
        $user = User::query()->findOrFail(10);

        $this->assertSame(
            ['Veterano Oro', 'GRA'],
            $user->metopas->pluck('name')->all(),
        );

        $this->assertSame(
            ['Veterano Oro', 'GRA'],
            User::query()->with('metopas')->findOrFail(10)->metopas->pluck('name')->all(),
        );

        $this->assertSame(4, DB::table('metopa_user')->where('user_id', 10)->count());
        $this->assertSame(2, DB::table('metopa_user')->where('user_id', 10)->whereNotNull('deleted_at')->count());

        // La relación inversa ya tenía este filtro. Ambas deben coincidir.
        $this->assertSame([10], Metopa::query()->findOrFail(3)->users()->pluck('users.id')->all());
        $this->assertSame([], Metopa::query()->findOrFail(2)->users()->pluck('users.id')->all());
    }

    public function test_signature_does_not_render_retired_veterancy_metopas(): void
    {
        $user = User::query()->with('metopas')->findOrFail(10);
        $user->setRelation('status', new Status(['name' => 'ACTIVO']));
        $user->setRelation('promo', null);

        $this->view('firmas.show', ['user' => $user])
            ->assertSee('Veterano Oro')
            ->assertSee('GRA')
            ->assertDontSee('Veterano Bronce')
            ->assertDontSee('Veterano Plata');
    }
}
