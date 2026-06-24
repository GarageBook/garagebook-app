<?php

namespace App\Console\Commands;

use Google\Client as GoogleClient;
use Illuminate\Console\Command;
use Throwable;

class GenerateGoogleRefreshTokenCommand extends Command
{
    protected $signature = 'garagebook:generate-google-refresh-token';

    protected $description = 'Genereer veilig een nieuwe Google OAuth refresh token voor GarageBook analytics sync.';

    public function handle(): int
    {
        $clientId = $this->clientId();
        $clientSecret = $this->clientSecret();

        if ($clientId === null) {
            $this->error('GOOGLE_ANALYTICS_CLIENT_ID ontbreekt.');

            return self::FAILURE;
        }

        if ($clientSecret === null) {
            $this->error('GOOGLE_ANALYTICS_CLIENT_SECRET ontbreekt.');

            return self::FAILURE;
        }

        $client = $this->makeGoogleClient($clientId, $clientSecret);

        $this->line('Open deze Google OAuth URL in je browser:');
        $this->newLine();
        $this->line($client->createAuthUrl());
        $this->newLine();
        $this->line('Log in met het Google-account dat toegang heeft tot GA4 property 538513059 en Search Console voor garagebook.nl/app.garagebook.nl.');

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
        $this->line('access-token-ok: '.(isset($token['access_token']) && is_string($token['access_token']) && $token['access_token'] !== '' ? 'ja' : 'nee'));

        if (isset($token['expires_in']) && is_numeric($token['expires_in'])) {
            $this->line('expires_in: '.(int) $token['expires_in']);
        }

        return self::SUCCESS;
    }

    protected function makeGoogleClient(string $clientId, string $clientSecret): GoogleClient
    {
        /** @var GoogleClient $client */
        $client = app(GoogleClient::class);
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setRedirectUri('http://localhost');
        $client->setScopes([
            'https://www.googleapis.com/auth/analytics.readonly',
            'https://www.googleapis.com/auth/webmasters.readonly',
        ]);
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        return $client;
    }

    private function clientId(): ?string
    {
        $clientId = config('services.google_analytics.client_id');

        return is_string($clientId) && trim($clientId) !== ''
            ? trim($clientId)
            : null;
    }

    private function clientSecret(): ?string
    {
        $clientSecret = config('services.google_analytics.client_secret');

        return is_string($clientSecret) && trim($clientSecret) !== ''
            ? trim($clientSecret)
            : null;
    }
}
