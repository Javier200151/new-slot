<?php

namespace App\Http\Controllers;

use App\Models\MemberProcedureSetting;
use App\Services\MemberProcedures\CommunicationPreviewService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MemberProcedureEmailPreviewController extends Controller
{
    public function __invoke(
        Request $request,
        string $type,
        CommunicationPreviewService $previews,
    ): Response {
        abort_unless(
            $request->user()?->can('member-procedure-settings.view') === true,
            403,
        );

        abort_unless(in_array($type, ['welcome', 'reactivation'], true), 404);

        $setting = MemberProcedureSetting::current();
        $preview = $previews->emailPreviews($setting)[$type] ?? null;
        abort_unless(is_array($preview) && isset($preview['html']), 404);

        return response((string) $preview['html'], 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
