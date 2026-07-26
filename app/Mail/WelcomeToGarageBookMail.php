<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class WelcomeToGarageBookMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.welcome_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome',
            with: [
                'user' => $this->user,
                'greetingName' => $this->greetingName(),
                'ctaUrl' => $this->ctaUrl(),
            ],
        );
    }

    public function greetingName(): ?string
    {
        $name = trim((string) $this->user->name);

        if ($name === '') {
            return null;
        }

        return Str::of($name)->before(' ')->trim()->toString() ?: $name;
    }

    public function ctaUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/admin';
    }
}
