<?php

namespace App\Mail;

use App\Models\ContactSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactMessageConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ContactSubmission $submission)
    {
    }

    public function envelope(): Envelope
    {
        $replyTo = trim((string) config('mail.contact_to', 'contactosquadalpha@gmail.com'));

        return new Envelope(
            subject: 'Hemos recibido tu consulta | Squad ALPHA',
            replyTo: $replyTo !== '' ? [new Address($replyTo, 'Squad ALPHA')] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-message-confirmation',
        );
    }
}
