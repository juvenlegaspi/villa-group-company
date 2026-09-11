<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $resetUrl = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Reset your Villa Group password')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('We received a request to reset the password for your Villa Group account.')
            ->action('Reset Password', $resetUrl)
            ->line('This secure link expires in '.config('auth.passwords.users.expire').' minutes.')
            ->line('If you did not request a password reset, you can safely ignore this email.')
            ->salutation('Regards, Villa Group Team');
    }
}
