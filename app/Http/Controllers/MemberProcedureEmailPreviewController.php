<?php

namespace App\Http\Controllers;

use App\Filament\Resources\MemberProcedureSettings\MemberProcedureSettingResource;
use App\Models\MemberProcedureSetting;
use App\Services\MemberProcedures\CommunicationPreviewService;
use App\Services\MemberProcedures\MemberProcedureEmailTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MemberProcedureEmailPreviewController extends Controller
{
    public function show(
        Request $request,
        string $type,
        CommunicationPreviewService $previews,
        MemberProcedureEmailTemplateService $templates,
    ): View {
        abort_unless($request->user()?->can('member-procedure-settings.view') === true, 403);
        abort_unless(in_array($type, ['welcome', 'reactivation'], true), 404);

        $setting = MemberProcedureSetting::current();
        $reactivation = $type === 'reactivation';
        $preview = $previews->emailPreview(
            new \App\Models\User(['nick' => 'Rylod', 'email' => 'rylod@example.com']),
            $setting,
            $reactivation,
        );
        $template = $templates->templates($setting, $reactivation);

        return view('filament.member-procedure-email-preview-page', [
            'type' => $type,
            'setting' => $setting,
            'preview' => $preview,
            'template' => $template,
            'editing' => $request->boolean('edit'),
            'canEdit' => $request->user()?->can('member-procedure-settings.update') === true,
            'backUrl' => MemberProcedureSettingResource::getUrl('edit', ['record' => $setting]),
        ]);
    }

    public function update(Request $request, string $type): RedirectResponse
    {
        abort_unless($request->user()?->can('member-procedure-settings.update') === true, 403);
        abort_unless(in_array($type, ['welcome', 'reactivation'], true), 404);

        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:6000'],
        ]);

        $setting = MemberProcedureSetting::current();
        $subject = trim((string) ($data['subject'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));

        if ($type === 'reactivation') {
            $setting->reactivation_email_subject = $subject !== '' ? $subject : null;
            $setting->reactivation_email_body = $body !== '' ? $body : null;
        } else {
            $setting->member_welcome_email_subject = $subject !== '' ? $subject : null;
            $setting->member_welcome_email_body = $body !== '' ? $body : null;
        }

        $setting->save();

        return redirect()
            ->route('member-procedure-email-preview.show', ['type' => $type])
            ->with('status', 'Texto del correo actualizado correctamente.');
    }
}
