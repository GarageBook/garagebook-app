<?php

namespace Tests\Unit;

use App\Services\Analytics\Ga4AnalyticsService;
use App\Services\Analytics\SearchConsoleService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class Ga4AnalyticsServiceConfigurationTest extends TestCase
{
    public function test_ga4_service_account_mode_is_configured_when_property_and_credentials_file_exist(): void
    {
        $path = storage_path('framework/testing/ga4-service-account.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, '{}');

        config([
            'services.google_analytics.auth_mode' => 'service_account',
            'services.google_analytics.property_id' => '123456789',
            'services.google_analytics.credentials_json' => $path,
        ]);

        $service = app(Ga4AnalyticsService::class);

        $this->assertTrue($service->isConfigured());
        $this->assertNull($service->configurationError());
    }

    public function test_ga4_oauth_mode_is_configured_when_required_values_are_present(): void
    {
        config([
            'services.google_analytics.auth_mode' => 'oauth',
            'services.google_analytics.property_id' => '123456789',
            'services.google_analytics.client_id' => 'client-id.apps.googleusercontent.com',
            'services.google_analytics.client_secret' => 'client-secret',
            'services.google_analytics.refresh_token' => 'refresh-token',
            'services.google_analytics.credentials_json' => null,
        ]);

        $service = app(Ga4AnalyticsService::class);

        $this->assertTrue($service->isConfigured());
        $this->assertNull($service->configurationError());
    }

    public function test_ga4_oauth_mode_reports_missing_refresh_token_clearly(): void
    {
        config([
            'services.google_analytics.auth_mode' => 'oauth',
            'services.google_analytics.property_id' => '123456789',
            'services.google_analytics.client_id' => 'client-id.apps.googleusercontent.com',
            'services.google_analytics.client_secret' => 'client-secret',
            'services.google_analytics.refresh_token' => '',
        ]);

        $service = app(Ga4AnalyticsService::class);

        $this->assertFalse($service->isConfigured());
        $this->assertSame(
            'GOOGLE_ANALYTICS_REFRESH_TOKEN ontbreekt voor Google OAuth authenticatie.',
            $service->configurationError(),
        );
    }

    public function test_ga4_oauth_mode_still_requires_property_id(): void
    {
        config([
            'services.google_analytics.auth_mode' => 'oauth',
            'services.google_analytics.property_id' => '',
            'services.google_analytics.client_id' => 'client-id.apps.googleusercontent.com',
            'services.google_analytics.client_secret' => 'client-secret',
            'services.google_analytics.refresh_token' => 'refresh-token',
        ]);

        $service = app(Ga4AnalyticsService::class);

        $this->assertFalse($service->isConfigured());
        $this->assertSame('GOOGLE_ANALYTICS_PROPERTY_ID ontbreekt.', $service->configurationError());
    }

    public function test_search_console_remains_service_account_based(): void
    {
        config([
            'services.search_console.site_url' => 'https://garagebook.nl/',
            'services.search_console.credentials_json' => null,
        ]);

        $service = app(SearchConsoleService::class);

        $this->assertFalse($service->isConfigured());
        $this->assertSame(
            'GOOGLE_SEARCH_CONSOLE_CREDENTIALS_JSON ontbreekt of verwijst niet naar een leesbaar bestand.',
            $service->configurationError(),
        );
    }
}
