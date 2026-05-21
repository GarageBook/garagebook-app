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
        return $this->configurationError() === null;
    }

    public function configurationError(): ?string
    {
        return match ($this->authMode()) {
            'service_account' => $this->serviceAccountConfigurationError(),
            'oauth' => $this->oauthConfigurationError(),
            default => sprintf(
                '%s heeft een ongeldige waarde [%s]. Gebruik service_account%s.',
                $this->authModeEnvVar(),
                $this->authMode(),
                $this->supportsOauth() ? ' of oauth' : ''
            ),
        };
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

    protected function authMode(): string
    {
        $mode = config('services.' . $this->configPrefix() . '.auth_mode', 'service_account');

        if (! is_string($mode) || trim($mode) === '') {
            return 'service_account';
        }

        return strtolower(trim($mode));
    }

    protected function supportsOauth(): bool
    {
        return false;
    }

    protected function envPrefix(): string
    {
        return strtoupper($this->configPrefix());
    }

    protected function accessToken(): string
    {
        $configurationError = $this->configurationError();

        if ($configurationError !== null) {
            throw new RuntimeException($configurationError);
        }

        return match ($this->authMode()) {
            'service_account' => $this->serviceAccountAccessToken(),
            'oauth' => $this->oauthAccessToken(),
            default => throw new RuntimeException('Onbekende Google API auth mode.'),
        };
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

    private function serviceAccountConfigurationError(): ?string
    {
        $credentialsPath = $this->credentialsPath();

        if ($credentialsPath === null || ! is_file($credentialsPath)) {
            return $this->credentialsEnvVar() . ' ontbreekt of verwijst niet naar een leesbaar bestand.';
        }

        return null;
    }

    private function oauthConfigurationError(): ?string
    {
        if (! $this->supportsOauth()) {
            return 'OAuth wordt niet ondersteund voor deze Google API integratie.';
        }

        foreach ([
            'client_id' => $this->clientIdEnvVar(),
            'client_secret' => $this->clientSecretEnvVar(),
            'refresh_token' => $this->refreshTokenEnvVar(),
        ] as $configKey => $envVar) {
            $value = config('services.' . $this->configPrefix() . '.' . $configKey);

            if (! is_string($value) || trim($value) === '') {
                return $envVar . ' ontbreekt voor Google OAuth authenticatie.';
            }
        }

        return null;
    }

    private function serviceAccountAccessToken(): string
    {
        $credentialsPath = $this->credentialsPath();

        $client = new GoogleClient();
        $client->setAuthConfig($credentialsPath);
        $client->setScopes($this->scopes());
        $token = $client->fetchAccessTokenWithAssertion();

        if (! is_array($token) || ! isset($token['access_token']) || ! is_string($token['access_token'])) {
            throw new RuntimeException('Kon geen Google API access token ophalen.');
        }

        return $token['access_token'];
    }

    private function oauthAccessToken(): string
    {
        $client = new GoogleClient();
        $client->setClientId((string) config('services.' . $this->configPrefix() . '.client_id'));
        $client->setClientSecret((string) config('services.' . $this->configPrefix() . '.client_secret'));
        $client->setScopes($this->scopes());
        $token = $client->fetchAccessTokenWithRefreshToken((string) config('services.' . $this->configPrefix() . '.refresh_token'));

        if (! is_array($token) || ! isset($token['access_token']) || ! is_string($token['access_token'])) {
            throw new RuntimeException('Kon geen Google OAuth access token ophalen.');
        }

        return $token['access_token'];
    }

    private function authModeEnvVar(): string
    {
        return $this->envPrefix() . '_AUTH_MODE';
    }

    private function credentialsEnvVar(): string
    {
        return $this->envPrefix() . '_CREDENTIALS_JSON';
    }

    private function clientIdEnvVar(): string
    {
        return $this->envPrefix() . '_CLIENT_ID';
    }

    private function clientSecretEnvVar(): string
    {
        return $this->envPrefix() . '_CLIENT_SECRET';
    }

    private function refreshTokenEnvVar(): string
    {
        return $this->envPrefix() . '_REFRESH_TOKEN';
    }
}
