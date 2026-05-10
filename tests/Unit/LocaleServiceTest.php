<?php

namespace Tests\Unit;

use App\Services\LocaleService;
use Tests\TestCase;

class LocaleServiceTest extends TestCase
{
    public function test_locale_service_uses_nl_as_default_and_en_as_fallback(): void
    {
        $service = app(LocaleService::class);

        $this->assertSame('nl', $service->default());
        $this->assertSame('en', $service->fallback());
        $this->assertSame('nl', $service->current());
    }

    public function test_locale_service_can_build_catalog_for_dashboard_file(): void
    {
        $service = app(LocaleService::class);

        $catalog = collect($service->translationCatalog('dashboard'))->keyBy('key');

        $this->assertSame('Welkom terug, :name', $catalog['dashboard.welcome_back']['values']['nl']);
        $this->assertSame('Welcome back, :name', $catalog['dashboard.welcome_back']['values']['en']);
        $this->assertSame('Zeitachse', $catalog['dashboard.timeline_heading']['values']['de']);
        $this->assertSame('Chronologie', $catalog['dashboard.timeline_heading']['values']['fr']);
    }
}
