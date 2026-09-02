<?php

namespace App\Http\Controllers;

use App\Models\Metopa;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;
use Illuminate\Http\Request;

class MetopaController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $metopas = Metopa::query()
            ->leftJoin('sqa_groups', function ($join): void {
                $join
                    ->on('sqa_groups.id', '=', 'metopas.sqa_group_id')
                    ->whereNull('sqa_groups.deleted_at');
            })
            ->select([
                'metopas.id',
                'metopas.name',
                'metopas.description',
                'metopas.image',
                'metopas.sqa_group_id',
            ])
            ->with('sqaGroup:id,name,display_order')
            ->when(
                filled($search),
                function ($query) use ($search): void {
                    $like = '%' . $search . '%';

                    $query->where(function ($query) use ($like): void {
                        $query
                            ->where('metopas.name', 'like', $like)
                            ->orWhere('metopas.description', 'like', $like);
                    });
                }
            )
            ->orderByRaw('sqa_groups.display_order IS NULL')
            ->orderBy('sqa_groups.display_order')
            ->orderBy('metopas.name')
            ->get();

        return view('metopas.index', compact('metopas', 'search'));
    }

    public function show(Metopa $metopa): View
    {
        $metopa->load([
            'sqaGroup',
            'users' => fn ($query) => $query
                ->whereHas('status', fn (Builder $query): Builder => $query
                    ->whereIn('name', [
                        'ACTIVO',
                        'RESERVA',
                        'CESADO',
                        'BAJA',
                    ]))
                ->with([
                    'status:id,name',
                    'mainSqaGroup',
                ])
                ->orderBy('metopa_user.assigned_at', 'asc')
                ->orderBy('users.nick', 'asc'),
        ]);

        $descriptionOne = filled($metopa->despag1)
            ? new HtmlString(
                RichContentRenderer::make($metopa->despag1)->toHtml()
            )
            : new HtmlString('');

        $descriptionTwo = filled($metopa->despag2)
            ? new HtmlString(
                RichContentRenderer::make($metopa->despag2)->toHtml()
            )
            : new HtmlString('');

        return view('metopas.show', compact(
            'metopa',
            'descriptionOne',
            'descriptionTwo',
        ));
    }
}
