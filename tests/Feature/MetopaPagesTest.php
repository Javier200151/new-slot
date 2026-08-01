<?php

namespace Tests\Feature;

use App\Models\Metopa;
use App\Models\SqaGroup;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\HtmlString;
use Tests\TestCase;

class MetopaPagesTest extends TestCase
{
    public function test_metopa_index_renders_dynamic_metopa_links(): void
    {
        $metopa = new Metopa([
            'name' => 'CIBI',
            'description' => 'Curso de Instrucción Básica de Infantería',
            'image' => 'metopas/cibi.png',
        ]);

        $metopa->setRelation('sqaGroup', new SqaGroup([
            'name' => 'GIA',
        ]));

        $view = $this->view('metopas.index', [
            'metopas' => collect([$metopa]),
        ]);

        $view
            ->assertSee('Curso de Instrucción Básica de Infantería')
            ->assertSee('Grupo GIA')
            ->assertSee(route('metopas.show', $metopa), escape: false)
            ->assertSee('storage/metopas/cibi.png', escape: false);
    }

    public function test_metopa_detail_renders_the_selected_metopa_content(): void
    {
        $metopa = new Metopa([
            'name' => 'CIBI',
            'description' => 'Curso de Instrucción Básica de Infantería',
            'image' => 'metopas/cibi.png',
            'image_large' => 'metopas/large/cibi.png',
            'despag1' => '<p>Primera descripción</p>',
            'despag2' => '<p>Segunda descripción</p>',
            'imgback' => 'metopas/backgrounds/cibi.jpg',
        ]);

        $metopa->setRelation('sqaGroup', new SqaGroup([
            'name' => 'Academia',
            'large_name' => 'Academia Squad ALPHA',
            'image' => 'sqa-groups/academia.png',
        ]));

        $firstAwardee = new User(['nick' => 'Alpha']);
        $firstAwardee->setRelation('status', new Status(['name' => 'ACTIVO']));
        $firstAwardee->setRelation('pivot', new Pivot([
            'assigned_at' => '2025-01-10 12:00:00',
        ]));

        $secondAwardee = new User(['nick' => 'Bravo']);
        $secondAwardee->setRelation('status', new Status(['name' => 'RESERVA']));
        $secondAwardee->setRelation('pivot', new Pivot([
            'assigned_at' => '2025-03-20 12:00:00',
        ]));

        $metopa->setRelation('users', collect([$firstAwardee, $secondAwardee]));

        $view = $this->view('metopas.show', [
            'metopa' => $metopa,
            'descriptionOne' => new HtmlString('<p>Primera descripción</p>'),
            'descriptionTwo' => new HtmlString('<p>Segunda descripción</p>'),
        ]);

        $view
            ->assertSee('Curso de Instrucción Básica de Infantería')
            ->assertSee('<p>Primera descripción</p>', escape: false)
            ->assertSee('storage/metopas/large/cibi.png', escape: false)
            ->assertSee('<p>Segunda descripción</p>', escape: false)
            ->assertSee('Mostrar miembros galardonados')
            ->assertSee('Alpha')
            ->assertSee('ACTIVO')
            ->assertSee('Concedida el 10/01/2025')
            ->assertSee('Bravo')
            ->assertSee('RESERVA')
            ->assertSee('Concedida el 20/03/2025')
            ->assertSeeInOrder(['Alpha', 'Bravo'])
            ->assertSee('storage/sqa-groups/academia.png', escape: false)
            ->assertSee('storage/metopas/backgrounds/cibi.jpg', escape: false);
    }
}
