<?php

namespace App\Filament\Widgets;

use App\Models\LifecycleEmailLog;
use App\Models\LifecycleEmailTemplate;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Schema;

class LifecycleEmailStatsWidget extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $heading = 'Lifecycle e-mailactivatie';

    protected ?string $description = 'Per lifecycle-key: queue-status, klikgedrag en activatie naar eerste onderhoudslog.';

    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = [
        'md' => 2,
        'xl' => 3,
    ];

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected function getStats(): array
    {
        $stats = self::calculateStats();
        $cards = [];

        foreach ($stats['email_keys'] as $emailKey => $row) {
            $conversionRate = $row['sent'] > 0
                ? round(($row['goal_completed'] / $row['sent']) * 100, 1)
                : 0.0;

            $cards[] = Stat::make($emailKey, sprintf('Q %d | S %d | F %d', $row['queued'], $row['sent'], $row['failed']))
                ->description(sprintf('Kliks %d | Goals %d | Conv %.1f%% | Unsubs %d', $row['clicked'], $row['goal_completed'], $conversionRate, $row['unsubscribed_after_send']))
                ->color($row['failed'] > 0 ? 'danger' : ($row['queued'] > 0 ? 'warning' : 'success'));
        }

        $cards[] = Stat::make('Voertuig zonder onderhoud', (string) $stats['users_with_vehicle_no_maintenance'])
            ->description('Gebruikers met minimaal 1 voertuig maar nog geen eerste onderhoudslog.')
            ->color('warning');

        return $cards;
    }

    public static function calculateStats(): array
    {
        $emailKeys = [
            LifecycleEmailTemplate::NO_VEHICLE_DAY2,
            LifecycleEmailTemplate::NO_VEHICLE_ADDED,
            LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3,
            LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14,
            LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_30,
            LifecycleEmailTemplate::AFTER_FIRST_MAINTENANCE_LOG,
            LifecycleEmailTemplate::INACTIVE_USER_RETURN,
        ];

        $stats = [
            'email_keys' => [],
            'users_with_vehicle_no_maintenance' => 0,
        ];

        foreach ($emailKeys as $emailKey) {
            $stats['email_keys'][$emailKey] = [
                'queued' => 0,
                'sent' => 0,
                'failed' => 0,
                'clicked' => 0,
                'goal_completed' => 0,
                'unsubscribed_after_send' => 0,
            ];
        }

        if (! Schema::hasTable('lifecycle_email_logs')) {
            return $stats;
        }

        $rows = LifecycleEmailLog::query()
            ->selectRaw('email_key, status, COUNT(*) as aggregate')
            ->whereIn('email_key', $emailKeys)
            ->groupBy('email_key', 'status')
            ->get();

        foreach ($rows as $row) {
            if (! isset($stats['email_keys'][$row->email_key])) {
                continue;
            }

            if (in_array($row->status, [LifecycleEmailLog::STATUS_QUEUED, LifecycleEmailLog::STATUS_PROCESSING], true)) {
                $stats['email_keys'][$row->email_key]['queued'] += (int) $row->aggregate;

                continue;
            }

            if ($row->status === LifecycleEmailLog::STATUS_SENT) {
                $stats['email_keys'][$row->email_key]['sent'] = (int) $row->aggregate;

                continue;
            }

            if ($row->status === LifecycleEmailLog::STATUS_FAILED) {
                $stats['email_keys'][$row->email_key]['failed'] = (int) $row->aggregate;
            }
        }

        $clickedRows = LifecycleEmailLog::query()
            ->selectRaw('email_key, COUNT(*) as aggregate')
            ->whereIn('email_key', $emailKeys)
            ->whereNotNull('clicked_at')
            ->groupBy('email_key')
            ->pluck('aggregate', 'email_key');

        foreach ($clickedRows as $emailKey => $aggregate) {
            $stats['email_keys'][$emailKey]['clicked'] = (int) $aggregate;
        }

        $goalRows = LifecycleEmailLog::query()
            ->selectRaw('email_key, COUNT(*) as aggregate')
            ->whereIn('email_key', $emailKeys)
            ->whereNotNull('goal_completed_at')
            ->groupBy('email_key')
            ->pluck('aggregate', 'email_key');

        foreach ($goalRows as $emailKey => $aggregate) {
            $stats['email_keys'][$emailKey]['goal_completed'] = (int) $aggregate;
        }

        if (Schema::hasTable('users')) {
            $unsubscribeRows = LifecycleEmailLog::query()
                ->join('users', 'users.id', '=', 'lifecycle_email_logs.user_id')
                ->selectRaw('lifecycle_email_logs.email_key, COUNT(*) as aggregate')
                ->whereIn('lifecycle_email_logs.email_key', $emailKeys)
                ->where('lifecycle_email_logs.status', LifecycleEmailLog::STATUS_SENT)
                ->whereNotNull('lifecycle_email_logs.sent_at')
                ->whereNotNull('users.lifecycle_emails_unsubscribed_at')
                ->whereColumn('users.lifecycle_emails_unsubscribed_at', '>=', 'lifecycle_email_logs.sent_at')
                ->groupBy('lifecycle_email_logs.email_key')
                ->pluck('aggregate', 'email_key');

            foreach ($unsubscribeRows as $emailKey => $aggregate) {
                $stats['email_keys'][$emailKey]['unsubscribed_after_send'] = (int) $aggregate;
            }
        }

        if (Schema::hasTable('users') && Schema::hasTable('vehicles') && Schema::hasTable('maintenance_logs')) {
            $stats['users_with_vehicle_no_maintenance'] = User::query()
                ->whereHas('vehicles')
                ->whereDoesntHave('vehicles.maintenanceLogs')
                ->count();
        }

        return $stats;
    }
}
