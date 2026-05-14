<?php

namespace App\Services\Analytics;

use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;

abstract class GoogleApiService
{
    abstract protected function configPrefix(): string;

    /**
     * @return list<string>
     */
    abstract protected function scopes(): array;

    public function isConfigured(): bool
    {
        return filled($this->credentialsPath()) && is_file($this->credentialsPath());
    }

    protected function credentialsPath(): ?string
    {
        $path = config('services.' . $this->configPrefix() . '.credentials_json');

        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        if (str_starts_with($path, 'storage/')) {
            return storage_path(substr($path, strlen('storage/')));
        }

        return base_path($path);
    }

    protected function accessToken(): string
    {
        $credentialsPath = $this->credentialsPath();

        if ($credentialsPath === null || ! is_file($credentialsPath)) {
            throw new RuntimeException('Google API credentials file ontbreekt of is niet leesbaar.');
        }

        $client = new GoogleClient();
        $client->setAuthConfig($credentialsPath);
        $client->setScopes($this->scopes());
        $token = $client->fetchAccessTokenWithAssertion();

        if (! is_array($token) || ! isset($token['access_token']) || ! is_string($token['access_token'])) {
            throw new RuntimeException('Kon geen Google API access token ophalen.');
        }

        return $token['access_token'];
    }

    protected function postJson(string $url, array $payload): array
    {
        $response = Http::withToken($this->accessToken())
            ->timeout(30)
            ->acceptJson()
            ->post($url, $payload)
            ->throw();

        $json = $response->json();

        return is_array($json) ? $json : [];
    }
}
