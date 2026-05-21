<?php

namespace Tests\Unit;

use App\Services\Analytics\SearchConsoleService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SearchConsoleServiceConfigurationTest extends TestCase
{
    public function test_search_console_service_account_mode_is_configured_when_site_url_and_credentials_file_exist(): void
    {
        $path = storage_path('framework/testing/search-console-service-account.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, '{}');

        config([
            'services.search_console.auth_mode' => 'service_account',
            'services.search_console.site_url' => 'https://garagebook.nl/',
            'services.search_console.credentials_json' => $path,
        ]);

        $service = app(SearchConsoleService::class);

        $this->assertTrue($service->isConfigured());
        $this->assertNull($service->configurationError());
    }

    public function test_search_console_oauth_mode_is_configured_when_required_values_are_present(): void
    {
        config([
            'services.search_console.auth_mode' => 'oauth',
            'services.search_console.site_url' => 'https://garagebook.nl/',
            'services.search_console.client_id' => 'client-id.apps.googleusercontent.com',
            'services.search_console.client_secret' => 'client-secret',
            'services.search_console.refresh_token' => 'refresh-token',
            'services.search_console.credentials_json' => null,
        ]);

        $service = app(SearchConsoleService::class);

        $this->assertTrue($service->isConfigured());
        $this->assertNull($service->configurationError());
    }

    public function test_search_console_oauth_mode_reports_missing_refresh_token_clearly(): void
    {
        config([
            'services.search_console.auth_mode' => 'oauth',
            'services.search_console.site_url' => 'https://garagebook.nl/',
            'services.search_console.client_id' => 'client-id.apps.googleusercontent.com',
            'services.search_console.client_secret' => 'client-secret',
            'services.search_console.refresh_token' => '',
        ]);

        $service = app(SearchConsoleService::class);

        $this->assertFalse($service->isConfigured());
        $this->assertSame(
            'GOOGLE_SEARCH_CONSOLE_REFRESH_TOKEN ontbreekt voor Google OAuth authenticatie.',
            $service->configurationError(),
        );
    }

    public function test_search_console_oauth_mode_still_requires_site_url(): void
    {
        config([
            'services.search_console.auth_mode' => 'oauth',
            'services.search_console.site_url' => '',
            'services.search_console.client_id' => 'client-id.apps.googleusercontent.com',
            'services.search_console.client_secret' => 'client-secret',
            'services.search_console.refresh_token' => 'refresh-token',
        ]);

        $service = app(SearchConsoleService::class);

        $this->assertFalse($service->isConfigured());
        $this->assertSame('GOOGLE_SEARCH_CONSOLE_SITE_URL ontbreekt.', $service->configurationError());
    }
}
