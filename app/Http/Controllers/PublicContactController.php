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
            'email' => ['required', 'email:rfc', 'max:255'],
            'message' => ['required', 'string', 'max:6000'],
            'accepted_privacy' => ['accepted'],
            'accepted_contact' => ['accepted'],
            'website' => ['nullable', 'max:0'],
        ];

        if ($recruitmentRequested) {
            $rules += [
                'accepted_rules' => ['accepted'],
                'is_adult' => ['accepted'],
                'accepts_contributions' => ['accepted'],
                'has_required_game_content' => ['accepted'],
                'tuesday_available' => ['required', 'boolean'],
                'friday_available' => ['required', 'boolean'],
                'has_previous_experience' => ['nullable', 'boolean'],
            ];
        }

        $validated = $request->validate($rules, [
            'accepted_privacy.accepted' => 'Debes aceptar la política de privacidad.',
            'accepted_contact.accepted' => 'Debes aceptar el consentimiento de contacto.',
        ]);

        $submission = ContactSubmission::create([
            'email' => $validated['email'],
            'message' => $validated['message'],
            'is_recruitment' => $recruitmentRequested,
            'accepted_rules' => $recruitmentRequested && $request->boolean('accepted_rules'),
            'is_adult' => $recruitmentRequested && $request->boolean('is_adult'),
            'accepts_contributions' => $recruitmentRequested && $request->boolean('accepts_contributions'),
            'has_required_game_content' => $recruitmentRequested && $request->boolean('has_required_game_content'),
            'tuesday_available' => $recruitmentRequested ? $request->boolean('tuesday_available') : null,
            'friday_available' => $recruitmentRequested ? $request->boolean('friday_available') : null,
            'has_previous_experience' => $recruitmentRequested && $request->boolean('has_previous_experience'),
            'accepted_privacy' => true,
            'accepted_contact' => true,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ]);

        // Utiliza exactamente el mismo mailer SMTP y remitente global que ya
        // usa Laravel para la verificación de correo. Solo cambia el receptor.
        $to = config('mail.contact_to', 'planamayorsquadalpha@gmail.com');
        $subject = $recruitmentRequested
            ? 'Nueva solicitud de alistamiento - Squad ALPHA'
            : 'Nueva consulta desde Squad ALPHA';

        $html = view('emails.contact-submission', compact('submission'))->render();

        Mail::html($html, function ($message) use ($to, $subject, $submission): void {
            $message->to($to)
                ->replyTo($submission->email)
                ->subject($subject);
        });

        return back()->with('contact_status', $recruitmentRequested
            ? 'Solicitud de alistamiento enviada correctamente.'
            : 'Consulta enviada correctamente.');
    }
}
