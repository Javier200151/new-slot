<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(Page $page): View
    {
        abort_unless($page->is_published, 404);

        $content = new HtmlString(
            RichContentRenderer::make($page->content)->toHtml(),
        );

        return view('pages.show', compact('page', 'content'));
    }
}
