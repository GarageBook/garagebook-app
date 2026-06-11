<?php

namespace App\Mail\Lifecycle;

use App\Models\LifecycleEmailTemplate;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

abstract class LifecycleEmailMailable extends Mailable
{
    use SerializesModels;

    public function __construct(
        public User $user,
        public LifecycleEmailTemplate $template,
        public string $ctaUrl,
        public string $unsubscribeUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->template->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lifecycle',
        );
    }
}
