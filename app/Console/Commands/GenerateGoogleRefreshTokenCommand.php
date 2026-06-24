<?php

namespace App\Console\Commands;

use Google\Client as GoogleClient;
use Illuminate\Console\Command;
use Throwable;

class GenerateGoogleRefreshTokenCommand extends Command
{
    protected $signature = 'garagebook:generate-google-refresh-token {--service=shared : shared, ga4 of search-console}';

    protected $description = 'Genereer veilig een nieuwe Google OAuth refresh token voor GarageBook analytics sync.';

    public function handle(): int
    {
        $service = $this->serviceOption();

        if ($service === null) {
            $this->error('Ongeldige --service waarde. Gebruik shared, ga4 of search-console.');

            return self::FAILURE;
        }

        if ($service === 'shared' && ! $this->sharedClientConfigIsConsistent()) {
            $this->error('Shared token generatie vereist dat GA4 en Search Console dezelfde OAuth client gebruiken. Maak GOOGLE_ANALYTICS_CLIENT_ID/SECRET gelijk aan GOOGLE_SEARCH_CONSOLE_CLIENT_ID/SECRET, of gebruik aparte tokens via --service=ga4 en --service=search-console.');

            return self::FAILURE;
        }

        $config = $this->serviceConfig($service);
        $clientId = $this->configValue($config['prefix'], 'client_id');
        $clientSecret = $this->configValue($config['prefix'], 'client_secret');

        if ($clientId === null) {
            $this->error($config['env_prefix'].'_CLIENT_ID ontbreekt.');

            return self::FAILURE;
        }

        if ($clientSecret === null) {
            $this->error($config['env_prefix'].'_CLIENT_SECRET ontbreekt.');

            return self::FAILURE;
        }

        $client = $this->makeGoogleClient($clientId, $clientSecret, $config['scopes']);

        $this->line('Service: '.$service);
        $this->line('OAuth client env prefix: '.$config['env_prefix']);
        $this->line('Client ID suffix: '.$this->suffix($clientId));
        $this->line('Scopes: '.implode(', ', $config['scopes']));
        $this->newLine();
        $this->line('Open deze Google OAuth URL in je browser:');
        $this->newLine();
        $this->line($client->createAuthUrl());
        $this->newLine();
        $this->line($config['instruction']);

        $authorizationCode = trim((string) $this->ask('Plak hier de authorization code'));

        if ($authorizationCode === '') {
            $this->error('Geen authorization code opgegeven.');

            return self::FAILURE;
        }

        try {
            $token = $client->fetchAccessTokenWithAuthCode($authorizationCode);
        } catch (Throwable) {
            $this->error('Kon authorization code niet omwisselen voor tokens. Controleer client config en probeer opnieuw.');

            return self::FAILURE;
        }

        if (isset($token['error'])) {
            $description = is_string($token['error_description'] ?? null)
                ? $token['error_description']
                : (string) $token['error'];

            $this->error('Google OAuth gaf een fout terug: '.$description);

            return self::FAILURE;
        }

        $refreshToken = $token['refresh_token'] ?? null;

        if (! is_string($refreshToken) || trim($refreshToken) === '') {
            $this->error('Google gaf geen refresh_token terug. Doorloop consent opnieuw met het juiste account en prompt=consent.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Nieuwe refresh token:');
        $this->line($refreshToken);
        $this->newLine();
        $this->line('refresh-token-lengte: '.strlen(trim($refreshToken)));
        $this->line('access-token-ok: '.(isset($token['access_token']) && is_string($token['access_token']) && $token['access_token'] !== '' ? 'ja' : 'nee'));

        if (isset($token['expires_in']) && is_numeric($token['expires_in'])) {
            $this->line('expires_in: '.(int) $token['expires_in']);
        }

        $this->newLine();
        $this->comment($config['env_instruction']);

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $scopes
     */
    protected function makeGoogleClient(string $clientId, string $clientSecret, array $scopes): GoogleClient
    {
        /** @var GoogleClient $client */
        $client = app(GoogleClient::class);
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setRedirectUri('http://localhost');
        $client->setScopes($scopes);
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        return $client;
    }

    private function serviceOption(): ?string
    {
        $service = strtolower(trim((string) $this->option('service')));

        return in_array($service, ['shared', 'ga4', 'search-console'], true) ? $service : null;
    }

    /**
     * @return array{prefix:string,env_prefix:string,scopes:list<string>,instruction:string,env_instruction:string}
     */
    private function serviceConfig(string $service): array
    {
        return match ($service) {
            'ga4' => [
                'prefix' => 'google_analytics',
                'env_prefix' => 'GOOGLE_ANALYTICS',
                'scopes' => ['https://www.googleapis.com/auth/analytics.readonly'],
                'instruction' => 'Log in met het Google-account dat toegang heeft tot GA4 property 538513059.',
                'env_instruction' => 'Zet deze token in GOOGLE_ANALYTICS_REFRESH_TOKEN.',
            ],
            'search-console' => [
                'prefix' => 'search_console',
                'env_prefix' => 'GOOGLE_SEARCH_CONSOLE',
                'scopes' => ['https://www.googleapis.com/auth/webmasters.readonly'],
                'instruction' => 'Log in met het Google-account dat toegang heeft tot Search Console voor garagebook.nl/app.garagebook.nl.',
                'env_instruction' => 'Zet deze token in GOOGLE_SEARCH_CONSOLE_REFRESH_TOKEN.',
            ],
            default => [
                'prefix' => 'google_analytics',
                'env_prefix' => 'GOOGLE_ANALYTICS',
                'scopes' => [
                    'https://www.googleapis.com/auth/analytics.readonly',
                    'https://www.googleapis.com/auth/webmasters.readonly',
                ],
                'instruction' => 'Log in met het Google-account dat toegang heeft tot GA4 property 538513059 en Search Console voor garagebook.nl/app.garagebook.nl.',
                'env_instruction' => 'Zet deze token in GOOGLE_ANALYTICS_REFRESH_TOKEN en GOOGLE_SEARCH_CONSOLE_REFRESH_TOKEN. Beide services moeten dezelfde OAuth client_id/client_secret gebruiken.',
            ],
        };
    }

    private function sharedClientConfigIsConsistent(): bool
    {
        $analyticsClientId = $this->configValue('google_analytics', 'client_id');
        $analyticsClientSecret = $this->configValue('google_analytics', 'client_secret');
        $searchClientId = $this->configValue('search_console', 'client_id');
        $searchClientSecret = $this->configValue('search_console', 'client_secret');

        if ($searchClientId === null && $searchClientSecret === null) {
            return true;
        }

        return $analyticsClientId !== null
            && $analyticsClientSecret !== null
            && hash_equals($analyticsClientId, (string) $searchClientId)
            && hash_equals($analyticsClientSecret, (string) $searchClientSecret);
    }

    private function configValue(string $prefix, string $key): ?string
    {
        $value = config('services.'.$prefix.'.'.$key);

        return is_string($value) && trim($value) !== ''
            ? trim($value)
            : null;
    }

    private function suffix(string $value): string
    {
        return substr($value, -12);
    }
}
