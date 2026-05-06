<?php

namespace App\Mail;

use App\Models\RedeemCode;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RedeemCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public RedeemCode $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Code Review redeem code');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.redeem_code',
            with: ['code' => $this->code],
        );
    }
}
