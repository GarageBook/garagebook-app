<?php

namespace Tests\Feature;

use App\Mail\WeeklyGrowthReportMail;
use App\Models\AnalyticsDailySummary;
use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Growth\GrowthDashboardData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendGrowthReportCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_command_sends_growth_report_to_configured_recipient(): void
    {
        config(['services.growth_report.recipient' => 'willemvanveelen@icloud.com']);

        Mail::fake();

        $this->artisan('garagebook:send-growth-report')
            ->expectsOutput('Growth report verzonden naar: willemvanveelen@icloud.com')
            ->assertSuccessful();

        Mail::assertSent(WeeklyGrowthReportMail::class, function (WeeklyGrowthReportMail $mail): bool {
            return $mail->hasTo('willemvanveelen@icloud.com');
        });
    }

    public function test_mail_contains_key_growth_metrics(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-06 09:00:00'));

        $user = User::factory()->create([
            'created_at' => now()->subDays(9),
            'first_booklet_downloaded_at' => now(),
            'last_login_at' => now(),
        ]);
        $otherUser = User::factory()->create([
            'created_at' => now()->subDays(5),
        ]);

        $publicVehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'CBR600F',
            'public_slug' => 'honda-cbr600f',
            'is_public' => true,
            'created_at' => now()->subDays(2),
        ]);
        $publicVehicle->forceFill([
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ])->saveQuietly();

        $privateVehicle = Vehicle::query()->create([
            'user_id' => $otherUser->id,
            'brand' => 'Yamaha',
            'model' => 'MT-07',
            'is_public' => false,
        ]);
        $privateVehicle->forceFill([
            'created_at' => now()->subDays(20),
            'updated_at' => now()->subDays(20),
        ])->saveQuietly();

        $firstLog = MaintenanceLog::query()->create([
            'vehicle_id' => $publicVehicle->id,
            'description' => 'Olie vervangen',
            'km_reading' => 12345,
            'maintenance_date' => now()->toDateString(),
            'reminder_enabled' => true,
            'interval_months' => 12,
        ]);
        $firstLog->forceFill([
            'created_at' => now()->subDays(6),
            'updated_at' => now()->subDays(6),
        ])->saveQuietly();

        $secondLog = MaintenanceLog::query()->create([
            'vehicle_id' => $publicVehicle->id,
            'description' => 'Remblokken vervangen',
            'km_reading' => 13000,
            'maintenance_date' => now()->toDateString(),
        ]);
        $secondLog->forceFill([
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ])->saveQuietly();

        $oldLog = MaintenanceLog::query()->create([
            'vehicle_id' => $privateVehicle->id,
            'description' => 'Ketting gespannen',
            'km_reading' => 20000,
            'maintenance_date' => now()->subDays(10)->toDateString(),
        ]);
        $oldLog->forceFill([
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ])->saveQuietly();

        AnalyticsDailySummary::query()->create([
            'date' => now()->toDateString(),
            'users' => 4,
            'sessions' => 10,
            'screen_page_views' => 20,
            'event_count' => 30,
        ]);

        $mail = new WeeklyGrowthReportMail(app(GrowthDashboardData::class)->weeklyGrowthReport());
        $text = $this->renderedMailText($mail);

        $this->assertStringContainsString('Totaal gebruikers', $text);
        $this->assertStringContainsString('Users met voertuig', $text);
        $this->assertStringContainsString('Users met actieve reminder', $text);
        $this->assertStringContainsString('Registratie → voertuig', $text);
        $this->assertStringContainsString('Extra product/SEO KPI’s', $text);
        $this->assertStringContainsString('Gem. onderhoudslogs per voertuig: 1,5', $text);
        $this->assertStringContainsString('Users met ≥2 onderhoudslogs: 1 (50,0%)', $text);
        $this->assertStringContainsString('Publieke voertuigen: 1 (50,0%)', $text);
        $this->assertStringContainsString('Publieke voertuigpagina’s: 1', $text);
        $this->assertStringContainsString('Indexeerbare voertuigpagina’s: 1', $text);
        $this->assertStringContainsString('Onderhoudslogs toegevoegd laatste 7 dagen: 2', $text);
        $this->assertStringContainsString('Nieuwe publieke pagina’s laatste 7 dagen: 1', $text);
        $this->assertStringContainsString('Gem. tijd tot eerste onderhoud: 3,0 dagen', $text);
        $this->assertStringContainsString('Gem. sessies per gebruiker: 2,5', $text);
        $this->assertStringContainsString('AI-onboarding acceptatie: n.v.t. — nog niet actief', $text);
        $this->assertStringContainsString('contentgroei positief', $text);
        $this->assertStringContainsString('Korte interpretatie', $text);
    }

    public function test_command_does_not_crash_with_zero_data(): void
    {
        config(['services.growth_report.recipient' => 'willemvanveelen@icloud.com']);

        Mail::fake();

        $this->artisan('garagebook:send-growth-report')->assertSuccessful();

        Mail::assertSent(WeeklyGrowthReportMail::class);

        $mail = new WeeklyGrowthReportMail(app(GrowthDashboardData::class)->weeklyGrowthReport());
        $text = $this->renderedMailText($mail);

        $this->assertStringContainsString('Gem. onderhoudslogs per voertuig: 0,0', $text);
        $this->assertStringContainsString('Users met ≥2 onderhoudslogs: 0 (0,0%)', $text);
        $this->assertStringContainsString('Publieke voertuigen: 0 (0,0%)', $text);
        $this->assertStringContainsString('Onderhoudslogs toegevoegd laatste 7 dagen: 0', $text);
        $this->assertStringContainsString('Gem. tijd tot eerste onderhoud: niet beschikbaar', $text);
        $this->assertStringContainsString('Gem. sessies per gebruiker: niet beschikbaar', $text);
    }

    private function renderedMailText(WeeklyGrowthReportMail $mail): string
    {
        return preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($mail->render())));
    }
}
