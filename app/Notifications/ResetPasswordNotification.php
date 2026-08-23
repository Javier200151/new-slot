<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword
    as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification
    extends BaseResetPassword
{
    public function toMail($notifiable)
    {
        $resetUrl =
            $this->resetUrl($notifiable);

        return (new MailMessage)
            ->subject(
                'Recupera tu contraseña | Squad ALPHA'
            )
            ->view(
                'emails.reset-password',
                [
                    'user' => $notifiable,
                    'resetUrl' => $resetUrl,
                    'expireMinutes' => config(
                        'auth.passwords.users.expire',
                        60
                    ),
                ]
            );
    }
}