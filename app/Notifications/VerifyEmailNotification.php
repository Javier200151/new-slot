<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends BaseVerifyEmail
{
    public function toMail($notifiable)
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Verifica tu correo electrónico | Squad ALPHA')
            ->view(
                'emails.verify-email',
                [
                    'user' => $notifiable,
                    'verificationUrl' => $verificationUrl,
                ]
            );
    }
}