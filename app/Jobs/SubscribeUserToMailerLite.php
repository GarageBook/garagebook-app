<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\MailerLite\MailerLiteClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SubscribeUserToMailerLite implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public User $user,
    ) {}

    public function handle(MailerLiteClient $mailerLite): void
    {
        $mailerLite->subscribeUser($this->user);
    }
}
