<?php

namespace Tests\Unit;

use App\Services\Analytics\GoogleApiService;
use Tests\TestCase;

class GoogleApiServicePathResolutionTest extends TestCase
{
    public function test_storage_relative_paths_are_resolved_via_storage_path(): void
    {
        config([
            'services.google_analytics.credentials_json' => 'storage/app/google/ga4-service-account.json',
        ]);

        $service = new class extends GoogleApiService
        {
            protected function configPrefix(): string
            {
                return 'google_analytics';
            }

            protected function scopes(): array
            {
                return [];
            }

            public function resolvedPath(): ?string
            {
                return $this->credentialsPath();
            }
        };

        $this->assertSame(
            storage_path('app/google/ga4-service-account.json'),
            $service->resolvedPath(),
        );
    }

    public function test_absolute_paths_remain_unchanged(): void
    {
        config([
            'services.google_analytics.credentials_json' => '/tmp/google/ga4.json',
        ]);

        $service = new class extends GoogleApiService
        {
            protected function configPrefix(): string
            {
                return 'google_analytics';
            }

            protected function scopes(): array
            {
                return [];
            }

            public function resolvedPath(): ?string
            {
                return $this->credentialsPath();
            }
        };

        $this->assertSame('/tmp/google/ga4.json', $service->resolvedPath());
    }

    public function test_other_relative_paths_still_use_base_path(): void
    {
        config([
            'services.google_analytics.credentials_json' => 'secrets/google/ga4.json',
        ]);

        $service = new class extends GoogleApiService
        {
            protected function configPrefix(): string
            {
                return 'google_analytics';
            }

            protected function scopes(): array
            {
                return [];
            }

            public function resolvedPath(): ?string
            {
                return $this->credentialsPath();
            }
        };

        $this->assertSame(
            base_path('secrets/google/ga4.json'),
            $service->resolvedPath(),
        );
    }
}
