<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    private string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $resetUrl = sprintf(
            '%s/reset-password?email=%s&token=%s',
            rtrim((string) config('app.frontend_url', config('app.url')), '/'),
            urlencode((string) $notifiable->email),
            urlencode($this->token),
        );

        return (new MailMessage())
            ->subject('Reset your Humoo password')
            ->line('A password reset was requested for your Humoo account.')
            ->action('Reset password', $resetUrl)
            ->line('If you did not request this reset, you can ignore this message.');
    }
}
