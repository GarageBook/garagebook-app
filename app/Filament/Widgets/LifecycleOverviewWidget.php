<?php

namespace App\Filament\Widgets;

use App\Models\LifecycleEmailLog;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Schema;

class LifecycleOverviewWidget extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $heading = 'Lifecycle overzicht';

    protected ?string $description = 'Interne beheerstatistieken voor lifecycle e-mails.';

    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = [
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
            Stat::make('Gebruikers zonder voertuig', (string) $stats['users_without_vehicle'])
                ->description('Geverifieerd, ouder dan 2 dagen en niet uitgeschreven.')
                ->color('warning'),
            Stat::make('Queued', (string) $stats['queued'])
                ->description('Lifecycle-mails klaar voor verzending.')
                ->color($stats['queued'] > 0 ? 'warning' : 'gray'),
            Stat::make('Vandaag verzonden', (string) $stats['sent_today'])
                ->description('Lifecycle-mails met sent_at vandaag.')
                ->color('success'),
            Stat::make('Failed', (string) $stats['failed'])
                ->description('Lifecycle-mails met mislukte status.')
                ->color($stats['failed'] > 0 ? 'danger' : 'gray'),
        ];
    }

    public static function calculateStats(): array
    {
        $stats = [
            'users_without_vehicle' => 0,
            'queued' => 0,
            'sent_today' => 0,
            'failed' => 0,
        ];

        if (Schema::hasTable('users') && Schema::hasTable('vehicles')) {
            $stats['users_without_vehicle'] = User::query()
                ->whereNotNull('email_verified_at')
                ->where('created_at', '<=', now()->subDays(2))
                ->whereNull('lifecycle_emails_unsubscribed_at')
                ->whereDoesntHave('vehicles')
                ->count();
        }

        if (! Schema::hasTable('lifecycle_email_logs')) {
            return $stats;
        }

        $stats['queued'] = LifecycleEmailLog::query()
            ->whereIn('status', [
                LifecycleEmailLog::STATUS_QUEUED,
                LifecycleEmailLog::STATUS_PROCESSING,
            ])
            ->count();

        $stats['sent_today'] = LifecycleEmailLog::query()
            ->where('status', LifecycleEmailLog::STATUS_SENT)
            ->whereDate('sent_at', today())
            ->count();

        $stats['failed'] = LifecycleEmailLog::query()
            ->where('status', LifecycleEmailLog::STATUS_FAILED)
            ->count();

        return $stats;
    }
}
