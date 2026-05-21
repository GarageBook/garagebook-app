<?php

namespace Tests\Feature;

use Google\Client as GoogleClient;
use Mockery;
use Tests\TestCase;

class Ga4OauthTokenCommandTest extends TestCase
{
    public function test_command_fails_when_client_id_is_missing(): void
    {
        config([
            'services.google_analytics.client_id' => '',
            'services.google_analytics.client_secret' => 'secret',
        ]);

        $this->artisan('garagebook:ga4-oauth-token')
            ->expectsOutput('GOOGLE_ANALYTICS_CLIENT_ID ontbreekt.')
            ->assertExitCode(1);
    }

    public function test_command_prints_refresh_token_after_successful_exchange(): void
    {
        config([
            'services.google_analytics.client_id' => 'client-id.apps.googleusercontent.com',
            'services.google_analytics.client_secret' => 'client-secret',
        ]);

        $client = Mockery::mock(GoogleClient::class);
        $client->shouldReceive('setClientId')->once()->with('client-id.apps.googleusercontent.com');
        $client->shouldReceive('setClientSecret')->once()->with('client-secret');
        $client->shouldReceive('setRedirectUri')->once()->with('http://localhost');
        $client->shouldReceive('setScopes')->once()->with([
            'https://www.googleapis.com/auth/analytics.readonly',
            'https://www.googleapis.com/auth/webmasters.readonly',
        ]);
        $client->shouldReceive('setAccessType')->once()->with('offline');
        $client->shouldReceive('setPrompt')->once()->with('consent');
        $client->shouldReceive('createAuthUrl')->once()->andReturn('https://accounts.google.com/o/oauth2/auth?example=1');
        $client->shouldReceive('fetchAccessTokenWithAuthCode')->once()->with('auth-code-123')->andReturn([
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token-xyz',
        ]);

        $this->app->instance(GoogleClient::class, $client);

        $this->artisan('garagebook:ga4-oauth-token')
            ->expectsOutput('Open deze Google OAuth URL in je browser:')
            ->expectsOutput('https://accounts.google.com/o/oauth2/auth?example=1')
            ->expectsQuestion('Plak hier de authorization code', 'auth-code-123')
            ->expectsOutput('Refresh token:')
            ->expectsOutput('refresh-token-xyz')
            ->expectsOutput('Zet dit token in GOOGLE_ANALYTICS_REFRESH_TOKEN en/of GOOGLE_SEARCH_CONSOLE_REFRESH_TOKEN.')
            ->assertSuccessful();
    }

    public function test_command_fails_when_google_does_not_return_refresh_token(): void
    {
        config([
            'services.google_analytics.client_id' => 'client-id.apps.googleusercontent.com',
            'services.google_analytics.client_secret' => 'client-secret',
        ]);

        $client = Mockery::mock(GoogleClient::class);
        $client->shouldReceive('setClientId')->once()->with('client-id.apps.googleusercontent.com');
        $client->shouldReceive('setClientSecret')->once()->with('client-secret');
        $client->shouldReceive('setRedirectUri')->once()->with('http://localhost');
        $client->shouldReceive('setScopes')->once()->with([
            'https://www.googleapis.com/auth/analytics.readonly',
            'https://www.googleapis.com/auth/webmasters.readonly',
        ]);
        $client->shouldReceive('setAccessType')->once()->with('offline');
        $client->shouldReceive('setPrompt')->once()->with('consent');
        $client->shouldReceive('createAuthUrl')->once()->andReturn('https://accounts.google.com/o/oauth2/auth?example=1');
        $client->shouldReceive('fetchAccessTokenWithAuthCode')->once()->with('auth-code-123')->andReturn([
            'access_token' => 'access-token',
        ]);

        $this->app->instance(GoogleClient::class, $client);

        $this->artisan('garagebook:ga4-oauth-token')
            ->expectsQuestion('Plak hier de authorization code', 'auth-code-123')
            ->expectsOutput('Google gaf geen refresh_token terug. Gebruik een OAuth client die offline access ondersteunt en doorloop opnieuw consent met prompt=consent.')
            ->assertExitCode(1);
    }
}
