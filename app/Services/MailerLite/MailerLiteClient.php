<?php

namespace App\Services\MailerLite;

use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

class MailerLiteClient
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    /**
     * @param  array<int, string>  $groups
     */
    public function subscribe(string $email, ?string $name = null, array $groups = []): void
    {
        $groups = array_values(array_filter(
            array_map(fn (mixed $groupId): ?string => filled($groupId) ? (string) $groupId : null, $groups),
        ));

        if ($groups === []) {
            throw new RuntimeException('MAILERLITE_GROUP_ID is niet ingesteld.');
        }

        $this->request()->post('/subscribers', [
            'email' => $email,
            'fields' => [
                'name' => $name,
            ],
            'groups' => $groups,
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
