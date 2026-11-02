<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\MemberProcedureSetting;
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

            // Lectura sin efectos secundarios: nunca generar cambios al visitar la página.
            $paymentSetting = MemberProcedureSetting::query()->first([
                'treasury_payment_iban',
                'treasury_payment_beneficiary',
                'treasury_payment_paypal_url',
            ]);
            $iban = strtoupper(preg_replace('/\s+/', '', trim((string) ($paymentSetting?->treasury_payment_iban ?? ''))));
            $iban = preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban) ? $iban : null;

            $paypalUrl = trim((string) ($paymentSetting?->treasury_payment_paypal_url ?? ''));
            $paypalParts = parse_url($paypalUrl);
            $paypalHost = strtolower((string) ($paypalParts['host'] ?? ''));
            if (! is_array($paypalParts)
                || strtolower((string) ($paypalParts['scheme'] ?? '')) !== 'https'
                || ! in_array($paypalHost, ['paypal.com', 'www.paypal.com', 'paypal.me', 'www.paypal.me'], true)) {
                $paypalUrl = null;
            }

            $treasuryPaymentMethods = [
                'iban' => $iban,
                'beneficiary' => $iban ? trim((string) ($paymentSetting?->treasury_payment_beneficiary ?? '')) : null,
                'paypal_url' => $paypalUrl,
            ];

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
                'treasuryPaymentMethods',
                'treasuryUnavailable',
                'treasuryPrivateVisible',
                'treasuryMember',
                'treasuryMemberUnavailable',
            ));
        }

        return view('pages.show', compact('page', 'content'));
    }
}
