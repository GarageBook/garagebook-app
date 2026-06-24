<?php

namespace Tests\Feature;

use Google\Client as GoogleClient;
use Mockery;
use Tests\TestCase;

class GenerateGoogleRefreshTokenCommandTest extends TestCase
{
    public function test_command_fails_when_client_id_is_missing(): void
    {
        config([
            'services.google_analytics.client_id' => '',
            'services.google_analytics.client_secret' => 'client-secret-value',
        ]);

        $this->artisan('garagebook:generate-google-refresh-token')
            ->expectsOutput('GOOGLE_ANALYTICS_CLIENT_ID ontbreekt.')
            ->assertExitCode(1);
    }

    public function test_command_prints_refresh_token_but_not_access_token_or_client_secret(): void
    {
        config([
            'services.google_analytics.client_id' => 'client-id.apps.googleusercontent.com',
            'services.google_analytics.client_secret' => 'client-secret-value',
        ]);

        $client = Mockery::mock(GoogleClient::class);
        $client->shouldReceive('setClientId')->once()->with('client-id.apps.googleusercontent.com');
        $client->shouldReceive('setClientSecret')->once()->with('client-secret-value');
        $client->shouldReceive('setRedirectUri')->once()->with('http://localhost');
        $client->shouldReceive('setScopes')->once()->with([
            'https://www.googleapis.com/auth/analytics.readonly',
            'https://www.googleapis.com/auth/webmasters.readonly',
        ]);
        $client->shouldReceive('setAccessType')->once()->with('offline');
        $client->shouldReceive('setPrompt')->once()->with('consent');
        $client->shouldReceive('createAuthUrl')->once()->andReturn('https://accounts.google.com/o/oauth2/auth?example=1');
        $client->shouldReceive('fetchAccessTokenWithAuthCode')->once()->with('auth-code-123')->andReturn([
            'access_token' => 'access-token-value',
            'refresh_token' => 'refresh-token-value',
            'expires_in' => 3600,
        ]);

        $this->app->instance(GoogleClient::class, $client);

        $this->artisan('garagebook:generate-google-refresh-token')
            ->expectsOutput('Open deze Google OAuth URL in je browser:')
            ->expectsOutput('https://accounts.google.com/o/oauth2/auth?example=1')
            ->expectsQuestion('Plak hier de authorization code', 'auth-code-123')
            ->expectsOutput('Nieuwe refresh token:')
            ->expectsOutput('refresh-token-value')
            ->expectsOutput('access-token-ok: ja')
            ->expectsOutput('expires_in: 3600')
            ->doesntExpectOutput('access-token-value')
            ->doesntExpectOutput('client-secret-value')
            ->assertSuccessful();
    }

    public function test_command_fails_when_google_does_not_return_refresh_token(): void
    {
        config([
            'services.google_analytics.client_id' => 'client-id.apps.googleusercontent.com',
            'services.google_analytics.client_secret' => 'client-secret-value',
        ]);

        $client = Mockery::mock(GoogleClient::class);
        $client->shouldReceive('setClientId')->once()->with('client-id.apps.googleusercontent.com');
        $client->shouldReceive('setClientSecret')->once()->with('client-secret-value');
        $client->shouldReceive('setRedirectUri')->once()->with('http://localhost');
        $client->shouldReceive('setScopes')->once()->with([
            'https://www.googleapis.com/auth/analytics.readonly',
            'https://www.googleapis.com/auth/webmasters.readonly',
        ]);
        $client->shouldReceive('setAccessType')->once()->with('offline');
        $client->shouldReceive('setPrompt')->once()->with('consent');
        $client->shouldReceive('createAuthUrl')->once()->andReturn('https://accounts.google.com/o/oauth2/auth?example=1');
        $client->shouldReceive('fetchAccessTokenWithAuthCode')->once()->with('auth-code-123')->andReturn([
            'access_token' => 'access-token-value',
        ]);

        $this->app->instance(GoogleClient::class, $client);

        $this->artisan('garagebook:generate-google-refresh-token')
            ->expectsQuestion('Plak hier de authorization code', 'auth-code-123')
            ->expectsOutput('Google gaf geen refresh_token terug. Doorloop consent opnieuw met het juiste account en prompt=consent.')
            ->doesntExpectOutput('access-token-value')
            ->assertExitCode(1);
    }
}
