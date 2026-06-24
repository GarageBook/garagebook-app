<?php

namespace App\Services\Analytics;

use Google\Client as GoogleClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

abstract class GoogleApiService
{
    private const DEFAULT_OAUTH_TOKEN_URI = 'https://oauth2.googleapis.com/token';

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

    /**
     * @return array<string, mixed>
     */
    public function diagnoseOAuth(): array
    {
        $diagnostics = [
            'service' => $this->configPrefix(),
            'auth_mode' => $this->authMode(),
            'supports_oauth' => $this->supportsOauth(),
            'token_uri' => $this->oauthTokenUri(),
            'scopes' => $this->scopes(),
            'config_present' => $this->oauthConfigPresence(),
            'client_id_suffix' => $this->clientIdSuffix(),
            'refresh_token_length' => $this->refreshTokenLength(),
            'configuration_error' => $this->configurationError(),
            'token_endpoint_reachable' => null,
            'token_exchange_success' => false,
            'http_status' => null,
            'google_error' => null,
            'google_error_description' => null,
        ];

        if ($this->authMode() !== 'oauth' || $diagnostics['configuration_error'] !== null) {
            return $diagnostics;
        }

        try {
            return $this->summarizeOAuthTokenResponse($this->oauthTokenResponse(), $diagnostics);
        } catch (ConnectionException $exception) {
            return [
                ...$diagnostics,
                'token_endpoint_reachable' => false,
                'google_error' => 'connection_failed',
                'google_error_description' => $exception->getMessage(),
            ];
        }
    }

    protected function credentialsPath(): ?string
    {
        $path = config('services.'.$this->configPrefix().'.credentials_json');

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
        $mode = config('services.'.$this->configPrefix().'.auth_mode', 'service_account');

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
            return $this->credentialsEnvVar().' ontbreekt of verwijst niet naar een leesbaar bestand.';
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
            $value = config('services.'.$this->configPrefix().'.'.$configKey);

            if (! is_string($value) || trim($value) === '') {
                return $envVar.' ontbreekt voor Google OAuth authenticatie.';
            }
        }

        return null;
    }

    private function serviceAccountAccessToken(): string
    {
        $credentialsPath = $this->credentialsPath();

        $client = new GoogleClient;
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
        try {
            $response = $this->oauthTokenResponse();
        } catch (ConnectionException $exception) {
            $diagnostics = [
                ...$this->baseOAuthDiagnostics(),
                'token_endpoint_reachable' => false,
                'google_error' => 'connection_failed',
                'google_error_description' => $exception->getMessage(),
            ];

            $this->logOAuthFailure($diagnostics);

            throw new RuntimeException($this->oauthFailureMessage($diagnostics), previous: $exception);
        }

        $diagnostics = $this->summarizeOAuthTokenResponse($response, $this->baseOAuthDiagnostics());
        $json = $response->json();
        $accessToken = is_array($json) ? ($json['access_token'] ?? null) : null;

        if ($response->successful() && is_string($accessToken) && $accessToken !== '') {
            return $accessToken;
        }

        $this->logOAuthFailure($diagnostics);

        throw new RuntimeException($this->oauthFailureMessage($diagnostics));
    }

    private function oauthTokenResponse(): Response
    {
        return Http::asForm()
            ->timeout(15)
            ->acceptJson()
            ->post($this->oauthTokenUri(), [
                'client_id' => (string) config('services.'.$this->configPrefix().'.client_id'),
                'client_secret' => (string) config('services.'.$this->configPrefix().'.client_secret'),
                'refresh_token' => (string) config('services.'.$this->configPrefix().'.refresh_token'),
                'grant_type' => 'refresh_token',
            ]);
    }

    private function oauthTokenUri(): string
    {
        $tokenUri = config('services.'.$this->configPrefix().'.token_uri');

        return is_string($tokenUri) && trim($tokenUri) !== ''
            ? trim($tokenUri)
            : self::DEFAULT_OAUTH_TOKEN_URI;
    }

    /**
     * @return array<string, bool>
     */
    private function oauthConfigPresence(): array
    {
        return [
            $this->clientIdEnvVar() => $this->filledConfig('client_id'),
            $this->clientSecretEnvVar() => $this->filledConfig('client_secret'),
            $this->refreshTokenEnvVar() => $this->filledConfig('refresh_token'),
            $this->tokenUriEnvVar() => $this->filledConfig('token_uri'),
        ];
    }

    private function filledConfig(string $key): bool
    {
        $value = config('services.'.$this->configPrefix().'.'.$key);

        return is_string($value) && trim($value) !== '';
    }

    private function clientIdSuffix(): ?string
    {
        $clientId = config('services.'.$this->configPrefix().'.client_id');

        if (! is_string($clientId) || trim($clientId) === '') {
            return null;
        }

        return substr(trim($clientId), -12);
    }

    private function refreshTokenLength(): ?int
    {
        $refreshToken = config('services.'.$this->configPrefix().'.refresh_token');

        if (! is_string($refreshToken) || trim($refreshToken) === '') {
            return null;
        }

        return strlen(trim($refreshToken));
    }

    /**
     * @return array<string, mixed>
     */
    private function baseOAuthDiagnostics(): array
    {
        return [
            'service' => $this->configPrefix(),
            'auth_mode' => $this->authMode(),
            'token_uri' => $this->oauthTokenUri(),
            'scopes' => $this->scopes(),
            'config_present' => $this->oauthConfigPresence(),
            'client_id_suffix' => $this->clientIdSuffix(),
            'refresh_token_length' => $this->refreshTokenLength(),
            'token_endpoint_reachable' => null,
            'token_exchange_success' => false,
            'http_status' => null,
            'google_error' => null,
            'google_error_description' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $diagnostics
     * @return array<string, mixed>
     */
    private function summarizeOAuthTokenResponse(Response $response, array $diagnostics): array
    {
        $json = $response->json();
        $accessToken = is_array($json) ? ($json['access_token'] ?? null) : null;

        return [
            ...$diagnostics,
            'token_endpoint_reachable' => true,
            'token_exchange_success' => $response->successful() && is_string($accessToken) && $accessToken !== '',
            'http_status' => $response->status(),
            'google_error' => is_array($json) && is_string($json['error'] ?? null) ? $json['error'] : null,
            'google_error_description' => is_array($json) && is_string($json['error_description'] ?? null) ? $json['error_description'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $diagnostics
     */
    private function logOAuthFailure(array $diagnostics): void
    {
        Log::warning('google_oauth_access_token_failed', $diagnostics);
    }

    /**
     * @param  array<string, mixed>  $diagnostics
     */
    private function oauthFailureMessage(array $diagnostics): string
    {
        $message = 'Kon geen Google OAuth access token ophalen voor '.$this->configPrefix().'.';

        if (($diagnostics['http_status'] ?? null) !== null) {
            $message .= ' HTTP status: '.$diagnostics['http_status'].'.';
        }

        if (is_string($diagnostics['google_error'] ?? null) && $diagnostics['google_error'] !== '') {
            $message .= ' Google error: '.$diagnostics['google_error'].'.';
        }

        if (is_string($diagnostics['google_error_description'] ?? null) && $diagnostics['google_error_description'] !== '') {
            $message .= ' Google error_description: '.$diagnostics['google_error_description'].'.';
        }

        return $message;
    }

    private function authModeEnvVar(): string
    {
        return $this->envPrefix().'_AUTH_MODE';
    }

    private function credentialsEnvVar(): string
    {
        return $this->envPrefix().'_CREDENTIALS_JSON';
    }

    private function clientIdEnvVar(): string
    {
        return $this->envPrefix().'_CLIENT_ID';
    }

    private function clientSecretEnvVar(): string
    {
        return $this->envPrefix().'_CLIENT_SECRET';
    }

    private function refreshTokenEnvVar(): string
    {
        return $this->envPrefix().'_REFRESH_TOKEN';
    }

    private function tokenUriEnvVar(): string
    {
        return $this->envPrefix().'_TOKEN_URI';
    }
}
