<?php

namespace App\Filament\Widgets;

use App\Models\LifecycleEmailLog;
use App\Models\LifecycleEmailTemplate;
use App\Models\User;
use App\Services\LifecycleEmailService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Schema;

class LifecycleEmailStatsWidget extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $heading = 'Lifecycle e-mailactivatie';

    protected ?string $description = 'Activatiekansen en verzonden lifecycle-mails binnen GarageBook.';

    protected int | string | array $columnSpan = 'full';

    protected int | array | null $columns = [
        'md' => 2,
        'xl' => 4,
    ];

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected function getStats(): array
    {
        $stats = self::calculateStats();

        return [
            Stat::make('Verzonden laatste 30 dagen', (string) $stats['sent_last_30_days'])
                ->description('Alle lifecycle-mails met status sent.')
                ->color('success'),
            Stat::make('Openstaand dag 3', (string) $stats['outstanding_day_3'])
                ->description('Users die nu de dag-3 lifecycle-mail kwalificeren.')
                ->color('warning'),
            Stat::make('Openstaand dag 14', (string) $stats['outstanding_day_14'])
                ->description('Users die nu de dag-14 lifecycle-mail kwalificeren.')
                ->color('warning'),
            Stat::make('Openstaand dag 30', (string) $stats['outstanding_day_30'])
                ->description('Users die nu de dag-30 lifecycle-mail kwalificeren.')
                ->color('danger'),
        ];
    }

    public static function calculateStats(): array
    {
        $stats = [
            'sent_last_30_days' => 0,
            'outstanding_day_3' => 0,
            'outstanding_day_14' => 0,
            'outstanding_day_30' => 0,
        ];

        if (! Schema::hasTable('lifecycle_email_logs')) {
            return $stats;
        }

        $stats['sent_last_30_days'] = LifecycleEmailLog::query()
            ->where('status', LifecycleEmailLog::STATUS_SENT)
            ->where('sent_at', '>=', now()->subDays(30))
            ->count();

        if (! Schema::hasTable('lifecycle_email_templates')) {
            return $stats;
        }

        $service = app(LifecycleEmailService::class);

        User::query()
            ->whereHas('vehicles')
            ->whereNull('lifecycle_emails_unsubscribed_at')
            ->orderBy('id')
            ->chunkById(100, function ($users) use (&$stats, $service): void {
                foreach ($users as $user) {
                    $emailKey = $service->resolveEligibleEmailKey($user);

                    match ($emailKey) {
                        LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3 => $stats['outstanding_day_3']++,
                        LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14 => $stats['outstanding_day_14']++,
                        LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_30 => $stats['outstanding_day_30']++,
                        default => null,
                    };
                }
            });

        return $stats;
    }
}
