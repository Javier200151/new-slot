<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MemberTelegramLinksMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, string>  $links
     */
    public function __construct(
        public User $user,
        public string $mailSubject,
        public string $mailBody,
        public array $links,
        public bool $reactivation = false,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(view: $this->reactivation ? 'emails.member-reactivation' : 'emails.member-welcome');
    }
}
