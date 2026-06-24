<?php

namespace Tests\Unit;

use App\Services\Analytics\GoogleApiService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class GoogleApiServiceOAuthTest extends TestCase
{
    public function test_missing_oauth_config_gives_clear_error(): void
    {
        config([
            'services.google_analytics.auth_mode' => 'oauth',
            'services.google_analytics.client_id' => 'client-id.apps.googleusercontent.com',
            'services.google_analytics.client_secret' => 'client-secret-value',
            'services.google_analytics.refresh_token' => '',
            'services.google_analytics.token_uri' => 'https://oauth2.googleapis.com/token',
        ]);

        $service = $this->service();

        $this->assertSame(
            'GOOGLE_ANALYTICS_REFRESH_TOKEN ontbreekt voor Google OAuth authenticatie.',
            $service->configurationError(),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GOOGLE_ANALYTICS_REFRESH_TOKEN ontbreekt voor Google OAuth authenticatie.');

        $service->token();
    }

    public function test_invalid_grant_response_gives_specific_safe_error_and_log_context(): void
    {
        config($this->oauthConfig());

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'error' => 'invalid_grant',
                'error_description' => 'Token has been expired or revoked.',
            ], 400),
        ]);

        Log::spy();

        try {
            $this->service()->token();
            $this->fail('Expected OAuth token failure.');
        } catch (RuntimeException $exception) {
            $message = $exception->getMessage();
        }

        $this->assertStringContainsString('HTTP status: 400', $message);
        $this->assertStringContainsString('Google error: invalid_grant', $message);
        $this->assertStringContainsString('Google error_description: Token has been expired or revoked.', $message);
        $this->assertStringNotContainsString('client-secret-value', $message);
        $this->assertStringNotContainsString('refresh-token-value', $message);

        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context): bool {
            $encoded = json_encode($context, JSON_THROW_ON_ERROR);

            return $message === 'google_oauth_access_token_failed'
                && ($context['http_status'] ?? null) === 400
                && ($context['google_error'] ?? null) === 'invalid_grant'
                && ! str_contains($encoded, 'client-secret-value')
                && ! str_contains($encoded, 'refresh-token-value');
        });
    }

    public function test_successful_token_response_returns_access_token(): void
    {
        config($this->oauthConfig());

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token-value',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]),
        ]);

        $this->assertSame('access-token-value', $this->service()->token());
    }

    public function test_diagnose_command_does_not_print_secrets(): void
    {
        config([
            ...$this->oauthConfig(),
            'services.search_console.auth_mode' => 'oauth',
            'services.search_console.site_url' => 'https://garagebook.nl/',
            'services.search_console.client_id' => 'search-client-id.apps.googleusercontent.com',
            'services.search_console.client_secret' => 'search-client-secret-value',
            'services.search_console.refresh_token' => 'search-refresh-token-value',
            'services.search_console.token_uri' => 'https://oauth2.googleapis.com/token',
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'error' => 'invalid_grant',
                'error_description' => 'Token has been expired or revoked.',
            ], 400),
        ]);

        $exitCode = Artisan::call('garagebook:diagnose-google-oauth');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Client ID suffix: rcontent.com', $output);
        $this->assertStringContainsString('Refresh token lengte: 19', $output);
        $this->assertStringContainsString('Google error: invalid_grant', $output);
        $this->assertStringContainsString('Google error_description: Token has been expired or revoked.', $output);
        $this->assertStringNotContainsString('client-secret-value', $output);
        $this->assertStringNotContainsString('refresh-token-value', $output);
        $this->assertStringNotContainsString('search-client-secret-value', $output);
        $this->assertStringNotContainsString('search-refresh-token-value', $output);
    }

    private function service(): GoogleApiService
    {
        return new class extends GoogleApiService
        {
            protected function configPrefix(): string
            {
                return 'google_analytics';
            }

            protected function scopes(): array
            {
                return ['scope-a'];
            }

            protected function supportsOauth(): bool
            {
                return true;
            }

            public function token(): string
            {
                return $this->accessToken();
            }
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function oauthConfig(): array
    {
        return [
            'services.google_analytics.auth_mode' => 'oauth',
            'services.google_analytics.property_id' => '123456789',
            'services.google_analytics.client_id' => 'client-id.apps.googleusercontent.com',
            'services.google_analytics.client_secret' => 'client-secret-value',
            'services.google_analytics.refresh_token' => 'refresh-token-value',
            'services.google_analytics.token_uri' => 'https://oauth2.googleapis.com/token',
        ];
    }
}
