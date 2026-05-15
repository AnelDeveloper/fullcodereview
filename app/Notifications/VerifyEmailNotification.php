<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends BaseVerifyEmail
{
    /**
     * Build the verification URL pointing at our API endpoint.
     * The endpoint validates the signature, marks the user verified,
     * and redirects to the SPA with `?verified=1`.
     */
    protected function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60 * 24), // 24h
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );
    }

    public function toMail($notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);
        $appName = config('app.name', 'QodeShark');

        return (new MailMessage())
            ->subject("Confirm your email for {$appName}")
            ->greeting("Welcome to {$appName}!")
            ->line("Confirm your email so we can send your code audit report PDFs.")
            ->action('Confirm email address', $url)
            ->line('If you did not create an account, no further action is required.');
    }
}
