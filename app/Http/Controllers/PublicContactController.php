<?php

namespace App\Http\Controllers;

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

        $submission = ContactSubmission::create([
            'nickname' => trim($validated['nickname']),
            'email' => $validated['email'],
            'message' => $validated['message'],
            'is_recruitment' => $recruitmentRequested,
            'full_name' => $recruitmentRequested ? trim($validated['full_name']) : null,
            'birth_date' => $recruitmentRequested ? $validated['birth_date'] : null,
            'residence' => $recruitmentRequested ? trim($validated['residence']) : null,
            'phone_whatsapp' => $recruitmentRequested ? trim($validated['phone_whatsapp']) : null,
            'how_heard_us' => $recruitmentRequested ? trim($validated['how_heard_us']) : null,
            'accepted_rules' => $recruitmentRequested && $request->boolean('accepted_rules'),
            'is_adult' => $recruitmentRequested && $request->boolean('is_adult'),
            'accepts_contributions' => $recruitmentRequested && $request->boolean('accepts_contributions'),
            'has_required_game_content' => $recruitmentRequested && $request->boolean('has_required_game_content'),
            'tuesday_available' => $recruitmentRequested ? $request->boolean('tuesday_available') : null,
            'friday_available' => $recruitmentRequested ? $request->boolean('friday_available') : null,
            'has_previous_experience' => $recruitmentRequested ? $request->boolean('has_previous_experience') : null,
            'experience_summary' => $recruitmentRequested ? trim($validated['experience_summary']) : null,
            'accepted_privacy' => true,
            'accepted_contact' => true,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ]);

        // Utiliza exactamente el mismo mailer SMTP y remitente global que ya
        // usa Laravel para verificación de correo y recuperación de contraseña.
        $to = config('mail.contact_to', 'contactosquadalpha@gmail.com');
        $subject = $recruitmentRequested
            ? 'Solicitud de alistamiento ' . $submission->nickname
            : 'Consulta de contacto ' . $submission->nickname;

        $html = view('emails.contact-submission', compact('submission'))->render();

        Mail::html($html, function ($message) use ($to, $subject, $submission): void {
            $message->to($to)
                ->replyTo($submission->email, $submission->nickname)
                ->subject($subject);
        });

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
