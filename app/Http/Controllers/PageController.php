<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Services\Treasury\TreasuryService;
use App\Support\BbcodeMarkup;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class PageController extends Controller
{
    public function show(Page $page, Request $request, TreasuryService $treasuryService): View
    {
        abort_unless($page->is_published, 404);

        $content = BbcodeMarkup::render($page->content);

        if (($page->template ?? 'content') === 'treasury') {
            abort_unless($treasuryService->canViewTreasuryPage($request->user()), 404);

            $treasury = null;
            $treasuryUnavailable = false;
            $treasuryMember = null;
            $treasuryMemberUnavailable = false;
            $treasuryPrivateVisible = false;

            try {
                $treasury = $treasuryService->publicOverview();
            } catch (Throwable $exception) {
                report($exception);
                $treasuryUnavailable = true;
            }

            $user = $request->user();
            if ($user) {
                $treasuryPrivateVisible = $treasuryService->canViewPrivateBalance($user);

                if ($treasuryPrivateVisible) {
                    try {
                        $treasuryMember = $treasuryService->memberOverview($user);
                    } catch (Throwable $exception) {
                        report($exception);
                        $treasuryMemberUnavailable = true;
                    }
                }
            }

            return view('pages.treasury', compact(
                'page',
                'content',
                'treasury',
                'treasuryUnavailable',
                'treasuryPrivateVisible',
                'treasuryMember',
                'treasuryMemberUnavailable',
            ));
        }

        return view('pages.show', compact('page', 'content'));
    }
}
