<?php

namespace Tests\Feature;

use App\Mail\WeeklyGrowthReportMail;
use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendGrowthReportCommandTest extends TestCase
{
    use RefreshDatabase;

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
        $user = User::factory()->create([
            'first_booklet_downloaded_at' => now(),
            'last_login_at' => now(),
        ]);

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'CBR600F',
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Olie vervangen',
            'km_reading' => 12345,
            'maintenance_date' => now()->toDateString(),
            'reminder_enabled' => true,
            'interval_months' => 12,
        ]);

        $mail = new WeeklyGrowthReportMail(app(\App\Support\Growth\GrowthDashboardData::class)->weeklyGrowthReport());
        $html = $mail->render();

        $this->assertStringContainsString('Totaal gebruikers', $html);
        $this->assertStringContainsString('Users met voertuig', $html);
        $this->assertStringContainsString('Users met actieve reminder', $html);
        $this->assertStringContainsString('Registratie → voertuig', $html);
        $this->assertStringContainsString('Korte interpretatie', $html);
    }

    public function test_command_does_not_crash_with_zero_data(): void
    {
        config(['services.growth_report.recipient' => 'willemvanveelen@icloud.com']);

        Mail::fake();

        $this->artisan('garagebook:send-growth-report')->assertSuccessful();

        Mail::assertSent(WeeklyGrowthReportMail::class);
    }
}
