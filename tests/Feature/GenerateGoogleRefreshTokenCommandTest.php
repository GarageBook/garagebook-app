<?php

namespace Tests\Feature;

use Google\Client as GoogleClient;
use Mockery;
use Tests\TestCase;

class GenerateGoogleRefreshTokenCommandTest extends TestCase
{
    public function test_shared_command_fails_when_client_id_is_missing(): void
    {
        config([
            'services.google_analytics.client_id' => '',
            'services.google_analytics.client_secret' => 'client-secret-value',
            'services.search_console.client_id' => '',
            'services.search_console.client_secret' => '',
        ]);

        $this->artisan('garagebook:generate-google-refresh-token')
            ->expectsOutput('GOOGLE_ANALYTICS_CLIENT_ID ontbreekt.')
            ->assertExitCode(1);
    }

    public function test_shared_command_fails_when_search_console_client_differs(): void
    {
        config([
            'services.google_analytics.client_id' => 'analytics-client-id.apps.googleusercontent.com',
            'services.google_analytics.client_secret' => 'analytics-client-secret',
            'services.search_console.client_id' => 'search-console-client-id.apps.googleusercontent.com',
            'services.search_console.client_secret' => 'search-console-client-secret',
        ]);

        $this->artisan('garagebook:generate-google-refresh-token')
            ->expectsOutput('Shared token generatie vereist dat GA4 en Search Console dezelfde OAuth client gebruiken. Maak GOOGLE_ANALYTICS_CLIENT_ID/SECRET gelijk aan GOOGLE_SEARCH_CONSOLE_CLIENT_ID/SECRET, of gebruik aparte tokens via --service=ga4 en --service=search-console.')
            ->assertExitCode(1);
    }

    public function test_shared_command_prints_refresh_token_but_not_access_token_or_client_secret(): void
    {
        config([
            'services.google_analytics.client_id' => 'client-id.apps.googleusercontent.com',
            'services.google_analytics.client_secret' => 'client-secret-value',
            'services.search_console.client_id' => 'client-id.apps.googleusercontent.com',
            'services.search_console.client_secret' => 'client-secret-value',
        ]);

        $client = $this->mockGoogleClient([
            'https://www.googleapis.com/auth/analytics.readonly',
            'https://www.googleapis.com/auth/webmasters.readonly',
        ]);
        $client->shouldReceive('fetchAccessTokenWithAuthCode')->once()->with('auth-code-123')->andReturn([
            'access_token' => 'access-token-value',
            'refresh_token' => 'refresh-token-value',
            'expires_in' => 3600,
        ]);

        $this->artisan('garagebook:generate-google-refresh-token')
            ->expectsOutput('Service: shared')
            ->expectsOutput('OAuth client env prefix: GOOGLE_ANALYTICS')
            ->expectsOutput('Client ID suffix: rcontent.com')
            ->expectsOutput('Open deze Google OAuth URL in je browser:')
            ->expectsOutput('https://accounts.google.com/o/oauth2/auth?example=1')
            ->expectsQuestion('Plak hier de authorization code', 'auth-code-123')
            ->expectsOutput('Nieuwe refresh token:')
            ->expectsOutput('refresh-token-value')
            ->expectsOutput('refresh-token-lengte: 19')
            ->expectsOutput('access-token-ok: ja')
            ->expectsOutput('expires_in: 3600')
            ->doesntExpectOutput('access-token-value')
            ->doesntExpectOutput('client-secret-value')
            ->assertSuccessful();
    }

    public function test_search_console_service_uses_search_console_client_and_scope(): void
    {
        config([
            'services.search_console.client_id' => 'search-console-client-id.apps.googleusercontent.com',
            'services.search_console.client_secret' => 'search-console-secret-value',
        ]);

        $client = Mockery::mock(GoogleClient::class);
        $client->shouldReceive('setClientId')->once()->with('search-console-client-id.apps.googleusercontent.com');
        $client->shouldReceive('setClientSecret')->once()->with('search-console-secret-value');
        $client->shouldReceive('setRedirectUri')->once()->with('http://localhost');
        $client->shouldReceive('setScopes')->once()->with([
            'https://www.googleapis.com/auth/webmasters.readonly',
        ]);
        $client->shouldReceive('setAccessType')->once()->with('offline');
        $client->shouldReceive('setPrompt')->once()->with('consent');
        $client->shouldReceive('createAuthUrl')->once()->andReturn('https://accounts.google.com/o/oauth2/auth?example=1');
        $client->shouldReceive('fetchAccessTokenWithAuthCode')->once()->with('auth-code-123')->andReturn([
            'refresh_token' => 'search-refresh-token-value',
        ]);

        $this->app->instance(GoogleClient::class, $client);

        $this->artisan('garagebook:generate-google-refresh-token', ['--service' => 'search-console'])
            ->expectsOutput('Service: search-console')
            ->expectsOutput('OAuth client env prefix: GOOGLE_SEARCH_CONSOLE')
            ->expectsQuestion('Plak hier de authorization code', 'auth-code-123')
            ->expectsOutput('search-refresh-token-value')
            ->expectsOutput('Zet deze token in GOOGLE_SEARCH_CONSOLE_REFRESH_TOKEN.')
            ->doesntExpectOutput('search-console-secret-value')
            ->assertSuccessful();
    }

    public function test_command_fails_when_google_does_not_return_refresh_token(): void
    {
        config([
            'services.google_analytics.client_id' => 'client-id.apps.googleusercontent.com',
            'services.google_analytics.client_secret' => 'client-secret-value',
            'services.search_console.client_id' => 'client-id.apps.googleusercontent.com',
            'services.search_console.client_secret' => 'client-secret-value',
        ]);

        $client = $this->mockGoogleClient([
            'https://www.googleapis.com/auth/analytics.readonly',
            'https://www.googleapis.com/auth/webmasters.readonly',
        ]);
        $client->shouldReceive('fetchAccessTokenWithAuthCode')->once()->with('auth-code-123')->andReturn([
            'access_token' => 'access-token-value',
        ]);

        $this->artisan('garagebook:generate-google-refresh-token')
            ->expectsQuestion('Plak hier de authorization code', 'auth-code-123')
            ->expectsOutput('Google gaf geen refresh_token terug. Doorloop consent opnieuw met het juiste account en prompt=consent.')
            ->doesntExpectOutput('access-token-value')
            ->assertExitCode(1);
    }

    /**
     * @param  list<string>  $scopes
     */
    private function mockGoogleClient(array $scopes): GoogleClient
    {
        $client = Mockery::mock(GoogleClient::class);
        $client->shouldReceive('setClientId')->once()->with('client-id.apps.googleusercontent.com');
        $client->shouldReceive('setClientSecret')->once()->with('client-secret-value');
        $client->shouldReceive('setRedirectUri')->once()->with('http://localhost');
        $client->shouldReceive('setScopes')->once()->with($scopes);
        $client->shouldReceive('setAccessType')->once()->with('offline');
        $client->shouldReceive('setPrompt')->once()->with('consent');
        $client->shouldReceive('createAuthUrl')->once()->andReturn('https://accounts.google.com/o/oauth2/auth?example=1');

        $this->app->instance(GoogleClient::class, $client);

        return $client;
    }
}
