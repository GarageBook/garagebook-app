<?php

namespace App\Console\Commands;

use Google\Client as GoogleClient;
use Illuminate\Console\Command;

class Ga4OauthTokenCommand extends Command
{
    protected $signature = 'garagebook:ga4-oauth-token';

    protected $description = 'Haal tijdelijk een Google OAuth refresh token op voor GA4 en Search Console.';

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

        if (! $this->searchConsoleClientMatchesAnalytics($clientId, $clientSecret)) {
            $this->error('garagebook:ga4-oauth-token gebruikt de GA4 OAuth client. Search Console gebruikt een andere OAuth client; gebruik php artisan garagebook:generate-google-refresh-token --service=ga4 en --service=search-console, of maak de client_id/client_secret env keys gelijk.');

            return self::FAILURE;
        }

        $this->line('OAuth client suffix: '.substr($clientId, -12));

        $client = $this->makeGoogleClient($clientId, $clientSecret);
        $consentUrl = $client->createAuthUrl();

        $this->line('Open deze Google OAuth URL in je browser:');
        $this->newLine();
        $this->line($consentUrl);
        $this->newLine();
        $this->line('Log in met garagebook.analytics@gmail.com, geef toegang voor GA4 en Search Console en kopieer daarna de authorization code uit de redirect URL.');

        $authorizationCode = trim((string) $this->ask('Plak hier de authorization code'));

        if ($authorizationCode === '') {
            $this->error('Geen authorization code opgegeven.');

            return self::FAILURE;
        }

        try {
            $token = $client->fetchAccessTokenWithAuthCode($authorizationCode);
        } catch (\Throwable $exception) {
            $this->error('Kon authorization code niet omwisselen voor tokens: '.$exception->getMessage());

            return self::FAILURE;
        }

        if (isset($token['error'])) {
            $description = $token['error_description'] ?? $token['error'];
            $this->error('Google OAuth gaf een fout terug: '.$description);

            return self::FAILURE;
        }

        $refreshToken = $token['refresh_token'] ?? null;

        if (! is_string($refreshToken) || trim($refreshToken) === '') {
            $this->error('Google gaf geen refresh_token terug. Gebruik een OAuth client die offline access ondersteunt en doorloop opnieuw consent met prompt=consent.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Refresh token:');
        $this->line($refreshToken);
        $this->newLine();
        $this->comment('Zet dit token in GOOGLE_ANALYTICS_REFRESH_TOKEN en/of GOOGLE_SEARCH_CONSOLE_REFRESH_TOKEN.');

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

    private function searchConsoleClientMatchesAnalytics(string $clientId, string $clientSecret): bool
    {
        $searchClientId = config('services.search_console.client_id');
        $searchClientSecret = config('services.search_console.client_secret');

        if ((! is_string($searchClientId) || trim($searchClientId) === '')
            && (! is_string($searchClientSecret) || trim($searchClientSecret) === '')) {
            return true;
        }

        return is_string($searchClientId)
            && is_string($searchClientSecret)
            && hash_equals($clientId, trim($searchClientId))
            && hash_equals($clientSecret, trim($searchClientSecret));
    }
}
