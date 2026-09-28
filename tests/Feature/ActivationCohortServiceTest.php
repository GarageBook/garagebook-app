<?php

namespace Tests\Feature;

use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\UserAttribution;
use App\Models\Vehicle;
use App\Services\Growth\ActivationCohortService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ActivationCohortServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_core_filter_excludes_admin_demo_and_outreach_attribution(): void
    {
        $this->userAt('2026-04-01 10:00:00 UTC');
        User::factory()->admin()->create(['created_at' => '2026-04-01 10:00:00']);
        User::factory()->outreachDemo()->create(['created_at' => '2026-04-01 10:00:00']);
        $outreach = $this->userAt('2026-04-01 10:00:00 UTC');
        UserAttribution::query()->create(['user_id' => $outreach->id, 'source' => 'demo']);

        $cohort = $this->cohort('2026-04', '2026-06-01 00:00:00 UTC');

        $this->assertSame(1, $cohort['core_users']);
    }

    public function test_cohort_month_uses_amsterdam_boundaries_across_dst(): void
    {
        $this->userAt('2026-03-31 21:30:00 UTC'); // 23:30 CEST, March
        $this->userAt('2026-03-31 22:30:00 UTC'); // 00:30 CEST, April

        $cohorts = collect(app(ActivationCohortService::class)->monthly(Carbon::parse('2026-05-01 UTC')));

        $this->assertSame(1, $cohorts->firstWhere('cohort_month', '2026-03')['core_users']);
        $this->assertSame(1, $cohorts->firstWhere('cohort_month', '2026-04')['core_users']);
    }

    public function test_window_eligibility_uses_elapsed_time_exact_boundaries_and_provisional_status(): void
    {
        $asOf = Carbon::parse('2026-07-25 12:00:00 UTC');
        $old = $this->userAt('2026-06-01 12:00:00 UTC');
        $young = $this->userAt('2026-06-30 12:00:00 UTC');
        $this->logAt($this->vehicleAt($old, '2026-06-01 13:00:00 UTC'), '2026-06-02 12:00:00 UTC');
        $this->logAt($this->vehicleAt($young, '2026-06-30 13:00:00 UTC'), '2026-07-07 12:00:00 UTC');

        $cohort = $this->cohort('2026-06', $asOf);

        $this->assertSame(['numerator' => 1, 'denominator' => 2, 'percentage' => 50.0, 'eligible_population' => 2, 'status' => 'mature'], $cohort['first_log_within_24h']);
        $this->assertSame(2, $cohort['first_log_within_7d']['numerator']);
        $this->assertSame(2, $cohort['first_log_within_7d']['denominator']);
        $this->assertSame(1, $cohort['first_log_within_30d']['denominator']);
        $this->assertSame('provisional', $cohort['first_log_within_30d']['status']);
    }

    public function test_import_suspected_user_counts_for_lifetime_but_not_timing(): void
    {
        $native = $this->userAt('2026-04-01 10:00:00 UTC');
        $imported = $this->userAt('2026-04-02 10:00:00 UTC', ['airtable_record_id' => 'rec-import']);
        $this->logAt($this->vehicleAt($native, '2026-04-01 11:00:00 UTC'), '2026-04-01 12:00:00 UTC');
        $this->logAt($this->vehicleAt($imported, '2026-04-02 11:00:00 UTC'), '2026-04-02 12:00:00 UTC');

        $cohort = $this->cohort('2026-04', '2026-06-01 00:00:00 UTC');

        $this->assertSame(2, $cohort['users_with_first_log']);
        $this->assertSame(1, $cohort['timing_eligible']);
        $this->assertSame(1, $cohort['timing_excluded']);
        $this->assertSame(1, $cohort['first_log_within_7d']['denominator']);
    }

    public function test_second_log_can_be_on_another_vehicle_and_multiple_vehicles_do_not_duplicate_users(): void
    {
        $user = $this->userAt('2026-05-01 10:00:00 UTC');
        $firstVehicle = $this->vehicleAt($user, '2026-05-01 11:00:00 UTC');
        $secondVehicle = $this->vehicleAt($user, '2026-05-02 11:00:00 UTC');
        $this->logAt($firstVehicle, '2026-05-03 10:00:00 UTC');
        $this->logAt($secondVehicle, '2026-05-04 10:00:00 UTC');

        $cohort = $this->cohort('2026-05', '2026-07-01 00:00:00 UTC');

        $this->assertSame(1, $cohort['users_with_vehicle']);
        $this->assertSame(1, $cohort['users_with_second_log']);
        $this->assertSame(100.0, $cohort['first_to_second_log']['percentage']);
    }

    public function test_impossible_event_order_is_excluded_from_timing_but_kept_in_lifetime_adoption(): void
    {
        $user = $this->userAt('2026-05-10 10:00:00 UTC');
        $vehicle = $this->vehicleAt($user, '2026-05-09 10:00:00 UTC');
        $this->logAt($vehicle, '2026-05-09 11:00:00 UTC');

        $cohort = $this->cohort('2026-05', '2026-07-01 00:00:00 UTC');

        $this->assertSame(1, $cohort['users_with_first_log']);
        $this->assertSame(0, $cohort['timing_eligible']);
        $this->assertSame(1, $cohort['anomalies']);
        $this->assertSame('n/a', $cohort['first_log_within_7d']['status']);
    }

    public function test_provisional_thirty_day_metric_uses_only_fourteen_eligible_users_out_of_twenty_two(): void
    {
        for ($i = 0; $i < 14; $i++) {
            $user = $this->userAt('2026-06-01 10:00:00 UTC');
            $this->logAt($this->vehicleAt($user, '2026-06-01 11:00:00 UTC'), '2026-06-02 10:00:00 UTC');
        }

        for ($i = 0; $i < 8; $i++) {
            $this->userAt('2026-06-30 10:00:00 UTC');
        }

        $cohort = $this->cohort('2026-06', '2026-07-15 10:00:00 UTC');

        $this->assertSame(22, $cohort['core_users']);
        $this->assertSame(14, $cohort['first_log_within_30d']['numerator']);
        $this->assertSame(14, $cohort['first_log_within_30d']['denominator']);
        $this->assertSame(100.0, $cohort['first_log_within_30d']['percentage']);
        $this->assertSame(22, $cohort['first_log_within_30d']['eligible_population']);
        $this->assertSame('provisional', $cohort['first_log_within_30d']['status']);
    }

    private function cohort(string $month, Carbon|string $asOf): array
    {
        $cohorts = app(ActivationCohortService::class)->monthly($asOf instanceof Carbon ? $asOf : Carbon::parse($asOf));

        return collect($cohorts)->firstWhere('cohort_month', $month);
    }

    private function userAt(string $time, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->forceFill(['created_at' => Carbon::parse($time), 'updated_at' => Carbon::parse($time)])->saveQuietly();

        return $user->fresh();
    }

    private function vehicleAt(User $user, string $time): Vehicle
    {
        $vehicle = Vehicle::query()->create(['user_id' => $user->id, 'brand' => 'Test', 'model' => 'Vehicle']);
        $vehicle->forceFill(['created_at' => Carbon::parse($time), 'updated_at' => Carbon::parse($time)])->saveQuietly();

        return $vehicle->fresh();
    }

    private function logAt(Vehicle $vehicle, string $time): MaintenanceLog
    {
        $log = MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Onderhoud',
            'maintenance_date' => Carbon::parse($time)->toDateString(),
            'km_reading' => 100,
        ]);
        $log->forceFill(['created_at' => Carbon::parse($time), 'updated_at' => Carbon::parse($time)])->saveQuietly();

        return $log->fresh();
    }
}
