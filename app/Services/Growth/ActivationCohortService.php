<?php

namespace App\Services\Growth;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ActivationCohortService
{
    public const DEFINITION_VERSION = 'activation-v1';

    public const TIMEZONE = 'Europe/Amsterdam';

    /**
     * @return list<array<string, mixed>>
     */
    public function monthly(?Carbon $asOf = null): array
    {
        $asOf = ($asOf ?? now())->copy();
        $users = User::query()
            ->coreFunnel()
            ->with(['vehicles.maintenanceLogs'])
            ->orderBy('created_at')
            ->get()
            ->groupBy(fn (User $user): string => $user->created_at->copy()->timezone(self::TIMEZONE)->format('Y-m'));

        return $users
            ->map(fn (Collection $cohort, string $month): array => $this->buildCohort($month, $cohort, $asOf))
            ->sortByDesc('cohort_month')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCohort(string $month, Collection $users, Carbon $asOf): array
    {
        $rows = $users->map(fn (User $user): array => $this->userTimeline($user));
        $timingEligible = $rows->where('timing_eligible', true);
        $withVehicle = $rows->whereNotNull('first_vehicle_at');
        $withFirstLog = $rows->whereNotNull('first_log_at');
        $withSecondLog = $rows->whereNotNull('second_log_at');
        $firstLogDurations = $timingEligible
            ->pluck('registration_to_first_log_hours')
            ->filter(fn ($hours): bool => $hours !== null)
            ->sort()
            ->values();
        $secondLogDurations = $timingEligible
            ->pluck('first_to_second_log_hours')
            ->filter(fn ($hours): bool => $hours !== null)
            ->sort()
            ->values();

        return [
            'cohort_month' => $month,
            'cohort_label' => Carbon::createFromFormat('Y-m', $month, self::TIMEZONE)->translatedFormat('M Y'),
            'core_users' => $users->count(),
            'timing_eligible' => $timingEligible->count(),
            'timing_excluded' => $rows->where('timing_eligible', false)->count(),
            'import_suspected' => $rows->where('import_suspected', true)->count(),
            'anomalies' => $rows->where('has_timing_anomaly', true)->count(),
            'users_with_vehicle' => $withVehicle->count(),
            'users_with_first_log' => $withFirstLog->count(),
            'users_with_second_log' => $withSecondLog->count(),
            'registration_to_vehicle' => $this->metric($withVehicle->count(), $users->count(), 'mature'),
            'registration_to_first_log' => $this->metric($withFirstLog->count(), $users->count(), 'mature'),
            'first_log_within_24h' => $this->windowMetric($timingEligible, $asOf, 24),
            'first_log_within_7d' => $this->windowMetric($timingEligible, $asOf, 24 * 7),
            'first_log_within_30d' => $this->windowMetric($timingEligible, $asOf, 24 * 30),
            'first_to_second_log' => $this->metric($withSecondLog->count(), $withFirstLog->count(), $withFirstLog->isEmpty() ? 'n/a' : 'mature'),
            'average_hours_to_first_log' => $firstLogDurations->isEmpty() ? null : round((float) $firstLogDurations->average(), 1),
            'median_hours_to_first_log' => $this->median($firstLogDurations),
            'average_hours_first_to_second_log' => $secondLogDurations->isEmpty() ? null : round((float) $secondLogDurations->average(), 1),
            'median_hours_first_to_second_log' => $this->median($secondLogDurations),
            'definition_version' => self::DEFINITION_VERSION,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userTimeline(User $user): array
    {
        $vehicles = $user->vehicles;
        $logs = $vehicles->flatMap->maintenanceLogs->sortBy(fn ($log) => [$log->created_at?->timestamp, $log->id])->values();
        $firstVehicleAt = $vehicles->min('created_at');
        $firstLogAt = $logs->get(0)?->created_at;
        $secondLogAt = $logs->get(1)?->created_at;
        $importSuspected = filled($user->airtable_record_id)
            || str_contains((string) $user->record_origin, 'import')
            || $vehicles->contains(fn ($vehicle): bool => filled($vehicle->airtable_record_id) || str_contains((string) $vehicle->record_origin, 'import'))
            || $logs->contains(fn ($log): bool => filled($log->airtable_record_id) || str_contains((string) $log->record_origin, 'import'));
        $hasTimingAnomaly = ($firstVehicleAt !== null && $firstVehicleAt->lt($user->created_at))
            || ($firstLogAt !== null && $firstLogAt->lt($user->created_at))
            || ($secondLogAt !== null && ($firstLogAt === null || $secondLogAt->lt($firstLogAt)));

        return [
            'registered_at' => $user->created_at,
            'first_vehicle_at' => $firstVehicleAt,
            'first_log_at' => $firstLogAt,
            'second_log_at' => $secondLogAt,
            'import_suspected' => $importSuspected,
            'has_timing_anomaly' => $hasTimingAnomaly,
            'timing_eligible' => ! $importSuspected && ! $hasTimingAnomaly,
            'registration_to_first_log_hours' => $firstLogAt !== null && ! $hasTimingAnomaly
                ? $user->created_at->diffInMinutes($firstLogAt, false) / 60
                : null,
            'first_to_second_log_hours' => $firstLogAt !== null && $secondLogAt !== null && ! $hasTimingAnomaly
                ? $firstLogAt->diffInMinutes($secondLogAt, false) / 60
                : null,
        ];
    }

    private function windowMetric(Collection $timingEligible, Carbon $asOf, int $windowHours): array
    {
        $eligible = $timingEligible->filter(
            fn (array $row): bool => $row['registered_at']->copy()->addHours($windowHours)->lte($asOf)
        );
        $converted = $eligible->filter(
            fn (array $row): bool => $row['registration_to_first_log_hours'] !== null
                && $row['registration_to_first_log_hours'] <= $windowHours
        )->count();

        $status = $eligible->isEmpty()
            ? 'n/a'
            : ($eligible->count() === $timingEligible->count() ? 'mature' : 'provisional');

        return $this->metric($converted, $eligible->count(), $status, $timingEligible->count());
    }

    private function metric(int $numerator, int $denominator, string $status, ?int $eligiblePopulation = null): array
    {
        return [
            'numerator' => $numerator,
            'denominator' => $denominator,
            'percentage' => $denominator > 0 ? round(($numerator / $denominator) * 100, 1) : null,
            'eligible_population' => $eligiblePopulation ?? $denominator,
            'status' => $denominator > 0 ? $status : 'n/a',
        ];
    }

    private function median(Collection $values): ?float
    {
        if ($values->isEmpty()) {
            return null;
        }

        $middle = intdiv($values->count(), 2);

        return round($values->count() % 2 === 1
            ? (float) $values[$middle]
            : ((float) $values[$middle - 1] + (float) $values[$middle]) / 2, 1);
    }
}
