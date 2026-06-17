<?php

namespace Tests\Feature;

use App\Models\OutreachProspect;
use App\Services\Outreach\OutreachProspectExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutreachProspectExportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_service_includes_demo_url_and_tracking_columns(): void
    {
        $prospect = OutreachProspect::factory()->create([
            'company_name' => 'Moto Export',
            'city' => 'Arnhem',
            'email' => 'info@motoexport.nl',
            'website' => 'motoexport.nl',
            'clicked_at' => '2026-06-17 10:00:00',
            'first_login_at' => '2026-06-17 10:05:00',
            'last_login_at' => '2026-06-17 10:06:00',
            'login_count' => 2,
        ]);

        $csv = app(OutreachProspectExportService::class)->toCsv(OutreachProspect::query());

        $this->assertStringContainsString('company_name,city,email,website,demo_url,clicked_at,first_login_at,last_login_at,login_count', $csv);
        $this->assertStringContainsString('Moto Export', $csv);
        $this->assertStringContainsString('Arnhem', $csv);
        $this->assertStringContainsString($prospect->demoUrl(), $csv);
        $this->assertStringContainsString('2026-06-17 10:05:00', $csv);
    }
}
