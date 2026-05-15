<?php

namespace App\Mail;

use App\Models\Analysis;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AnalysisReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Analysis $analysis) {}

    public function envelope(): Envelope
    {
        $brand = config('codereview.brand_name', config('app.name', 'QodeShark'));
        $support = config('codereview.support_email', 'hello@qodeshark.com');

        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address($support, $brand),
            subject: "Code audit report — {$this->analysis->repo_full_name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.analysis_report',
            with: ['a' => $this->analysis],
        );
    }

    public function attachments(): array
    {
        $pdf = Pdf::loadView('reports.analysis_pdf', ['a' => $this->analysis]);
        $filename = str_replace('/', '_', $this->analysis->repo_full_name) . '-codereview.pdf';

        return [
            \Illuminate\Mail\Mailables\Attachment::fromData(fn () => $pdf->output(), $filename)
                ->withMime('application/pdf'),
        ];
    }
}
