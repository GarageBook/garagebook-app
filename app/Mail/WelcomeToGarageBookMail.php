<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeToGarageBookMail extends Mailable
{
    use SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welkom bij GarageBook',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome',
        );
    }
}