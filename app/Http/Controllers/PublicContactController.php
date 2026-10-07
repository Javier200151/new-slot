<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessageAdminMail;
use App\Mail\ContactMessageConfirmationMail;
use App\Mail\ContactSubmissionAdminMail;
use App\Mail\ContactSubmissionConfirmationMail;
use App\Models\ContactSubmission;
use App\Models\HomepageSetting;
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

        if ($recruitmentRequested) {
            $rules += [
                'full_name' => ['required', 'string', 'max:160'],
                'birth_date' => ['required', 'date', 'after_or_equal:1900-01-01', 'before_or_equal:' . now()->subYears(18)->toDateString()],
                'residence' => ['required', 'string', 'max:160'],
                'phone_whatsapp' => ['required', 'string', 'max:40', 'regex:/^[0-9+() .\-]{7,40}$/'],
                'discord_profile' => ['nullable', 'string', 'max:160'],
                'how_heard_us' => ['required', 'string', 'max:1500'],
                'accepted_rules' => ['accepted'],
                'is_adult' => ['accepted'],
                'accepts_contributions' => ['accepted'],
                'has_required_game_content' => ['accepted'],
                'tuesday_available' => ['required', 'boolean'],
                'friday_available' => ['required', 'boolean'],
                'has_previous_experience' => ['required', 'boolean'],
                'experience_summary' => ['required', 'string', 'max:4000'],
            ];
        }

        $validated = $request->validate($rules, [
            'nickname.required' => 'Indica el nickname por el que debemos conocerte.',
            'email.required' => 'Indica un email de contacto.',
            'full_name.required' => 'Indica tu nombre y apellidos reales.',
            'birth_date.required' => 'Indica tu fecha de nacimiento.',
            'birth_date.before_or_equal' => 'Para solicitar el alistamiento debes ser mayor de edad.',
            'residence.required' => 'Indica tu lugar de residencia.',
            'phone_whatsapp.required' => 'Indica un teléfono de contacto con WhatsApp.',
            'phone_whatsapp.regex' => 'El teléfono de contacto no tiene un formato válido.',
            'how_heard_us.required' => 'Cuéntanos cómo conociste Squad ALPHA.',
            'experience_summary.required' => 'Resume tu experiencia en simulación militar con Arma 3. Si no tienes experiencia, indícalo.',
            'accepted_rules.accepted' => 'Debes aceptar la normativa de Squad ALPHA.',
            'is_adult.accepted' => 'Debes confirmar que eres mayor de edad.',
            'accepts_contributions.accepted' => 'Debes aceptar la condición relativa a las aportaciones económicas.',
            'has_required_game_content.accepted' => 'Debes confirmar el requisito de Arma 3 original y los DLC/CDLC indicados.',
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
            $submissionData += [
                'full_name' => trim($validated['full_name']),
                'birth_date' => $validated['birth_date'],
                'residence' => trim($validated['residence']),
                'phone_whatsapp' => trim($validated['phone_whatsapp']),
                'discord_profile' => trim((string) ($validated['discord_profile'] ?? '')) ?: null,
                'how_heard_us' => trim($validated['how_heard_us']),
                'accepted_rules' => $request->boolean('accepted_rules'),
                'is_adult' => $request->boolean('is_adult'),
                'accepts_contributions' => $request->boolean('accepts_contributions'),
                'has_required_game_content' => $request->boolean('has_required_game_content'),
                'tuesday_available' => $request->boolean('tuesday_available'),
                'friday_available' => $request->boolean('friday_available'),
                'has_previous_experience' => $request->boolean('has_previous_experience'),
                'experience_summary' => trim($validated['experience_summary']),
            ];
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
