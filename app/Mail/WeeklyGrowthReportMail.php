<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WeeklyGrowthReportMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public array $report,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'GarageBook wekelijkse activation/retention rapportage',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.weekly-growth-report',
        );
    }
}
