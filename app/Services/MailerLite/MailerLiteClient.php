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
     * @param  array<string, string>  $fields
     */
    public function subscribe(string $email, ?string $name = null, array $groups = [], array $fields = []): void
    {
        $groups = array_values(array_filter(
            array_map(fn (mixed $groupId): ?string => filled($groupId) ? (string) $groupId : null, $groups),
        ));
        $fields = array_filter([
            'name' => $name,
            ...$fields,
        ], fn (mixed $value): bool => filled($value));

        if ($groups === []) {
            throw new RuntimeException('MAILERLITE_GROUP_ID is niet ingesteld.');
        }

        $this->request()->post('/subscribers', [
            'email' => $email,
            'fields' => $fields,
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
