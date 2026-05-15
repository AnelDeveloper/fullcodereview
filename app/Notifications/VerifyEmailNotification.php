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
        // Brand name lives in config (not env) so the sender display, subject,
        // and body all stay consistent regardless of APP_NAME / MAIL_FROM_NAME
        // on the deployment platform.
        $brand = config('codereview.brand_name', config('app.name', 'QodeShark'));
        $support = config('codereview.support_email', 'hello@qodeshark.com');

        return (new MailMessage())
            ->from($support, $brand)
            ->subject("Confirm your email — {$brand}")
            ->view('mail.verify_email', [
                'url'   => $url,
                'brand' => $brand,
                'user'  => $notifiable,
            ]);
    }
}
