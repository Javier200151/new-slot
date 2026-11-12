<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessageAdminMail;
use App\Mail\ContactMessageConfirmationMail;
use App\Mail\ContactSubmissionAdminMail;
use App\Mail\ContactSubmissionConfirmationMail;
use App\Models\ContactSubmission;
use App\Models\HomepageSetting;
use App\Support\RecruitmentFormFields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PublicContactController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $settings = HomepageSetting::current();
        $recruitmentRequested = $settings->recruitment_open && $request->boolean('is_recruitment');

        $rules = [
            'nickname' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'message' => ['required', 'string', 'max:6000'],
            'accepted_privacy' => ['accepted'],
            'accepted_contact' => ['accepted'],
            'website' => ['nullable', 'max:0'],
        ];

        $recruitmentFields = $recruitmentRequested ? RecruitmentFormFields::visible() : [];
        if ($recruitmentRequested) {
            $rules += RecruitmentFormFields::validationRules($recruitmentFields);
        }

        $validated = $request->validate($rules, [
            'nickname.required' => 'Indica el nickname por el que debemos conocerte.',
            'email.required' => 'Indica un email de contacto.',
            'recruitment_answers.*.required' => 'Completa esta pregunta del alistamiento.',
            'recruitment_answers.*.accepted' => 'Debes marcar esta casilla para continuar.',
            'recruitment_answers.*.before_or_equal' => 'Para solicitar el alistamiento debes ser mayor de edad.',
            'accepted_privacy.accepted' => 'Debes aceptar la política de privacidad.',
            'accepted_contact.accepted' => 'Debes aceptar el consentimiento de contacto.',
        ]);

        // Contacto general y alistamiento comparten la tabla histórica, pero no
        // deben compartir datos funcionales. Para una consulta simple guardamos
        // únicamente los campos de contacto; así los defaults de la tabla se
        // encargan de los campos de reclutamiento NOT NULL y no introducimos
        // valores NULL inválidos como `has_previous_experience`.
        $submissionData = [
            'nickname' => trim($validated['nickname']),
            'email' => $validated['email'],
            'message' => $validated['message'],
            'is_recruitment' => $recruitmentRequested,
            'accepted_privacy' => true,
            'accepted_contact' => true,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ];

        if ($recruitmentRequested) {
            $submissionData['recruitment_answers'] = RecruitmentFormFields::snapshot($recruitmentFields, $validated);
            $answers = (array) ($validated['recruitment_answers'] ?? []);

            // Keep existing columns populated for recruitment workflows that still use them.
            // Removed or hidden questions are not required and are left at DB defaults.
            foreach ($recruitmentFields as $field) {
                $key = $field['key'];
                if (! array_key_exists($key, RecruitmentFormFields::LEGACY_TYPES)) {
                    continue;
                }
                $value = $answers[$key] ?? null;
                $submissionData[$key] = $field['type'] === 'checkbox'
                    ? filter_var($value ?? false, FILTER_VALIDATE_BOOLEAN)
                    : (is_string($value) && trim($value) !== '' ? trim($value) : null);
            }
        }

        $submission = ContactSubmission::create($submissionData);

        // Utiliza exactamente el mismo mailer SMTP y remitente global que ya
        // usa Laravel para verificación de correo y recuperación de contraseña.
        $to = config('mail.contact_to', 'contactosquadalpha@gmail.com');

        if ($recruitmentRequested) {
            Mail::to($to)->send(new ContactSubmissionAdminMail($submission));
            Mail::to($submission->email)->send(new ContactSubmissionConfirmationMail($submission));
        } else {
            // Las consultas generales usan su propio flujo y sus propias vistas.
            // De esta forma no dependen de ningún dato o lógica de alistamiento.
            Mail::to($to)->send(new ContactMessageAdminMail($submission));
            Mail::to($submission->email)->send(new ContactMessageConfirmationMail($submission));
        }

        if ($recruitmentRequested && $request->user() === null) {
            return redirect()
                ->to(route('home', ['modal' => 'register']) . '#alistamiento')
                ->with('contact_status', 'Solicitud de alistamiento enviada correctamente. Crea ahora tu cuenta para continuar con el proceso.')
                ->with('recruitment_register_nick', $submission->nickname)
                ->with('recruitment_register_email', $submission->email);
        }

        return back()->with('contact_status', $recruitmentRequested
            ? 'Solicitud de alistamiento enviada correctamente.'
            : 'Consulta enviada correctamente.');
    }
}
