<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Support\BbcodeMarkup;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(Page $page): View
    {
        abort_unless($page->is_published, 404);

        $content = BbcodeMarkup::render($page->content);

        return view('pages.show', compact('page', 'content'));
    }
}
