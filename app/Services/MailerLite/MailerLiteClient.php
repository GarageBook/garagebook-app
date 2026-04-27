<?php

namespace App\Services\MailerLite;

use App\Models\User;
use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

class MailerLiteClient
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    public function subscribeUser(User $user): void
    {
        $groupId = config('services.mailerlite.group_id');

        if (blank($groupId)) {
            throw new RuntimeException('MAILERLITE_GROUP_ID is niet ingesteld.');
        }

        $this->request()->post('/subscribers', [
            'email' => $user->email,
            'fields' => [
                'name' => $user->name,
            ],
            'groups' => [(string) $groupId],
        ])->throw();
    }

    private function request()
    {
        $token = config('services.mailerlite.token');

        if (blank($token)) {
            throw new RuntimeException('MAILERLITE_API_TOKEN is niet ingesteld.');
        }

        return $this->http
            ->baseUrl(config('services.mailerlite.base_url'))
            ->withToken($token)
            ->acceptJson();
    }
}
