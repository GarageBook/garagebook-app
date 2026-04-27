<?php

namespace App\Jobs;

use App\Services\MailerLite\MailerLiteClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SubscribeUserToMailerLite implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public string $email,
        public ?string $name = null,
    ) {}

    public function handle(MailerLiteClient $mailerLite): void
    {
        $mailerLite->subscribe($this->email, $this->name);
    }
}
