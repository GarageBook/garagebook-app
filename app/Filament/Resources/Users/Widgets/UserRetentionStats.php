<?php

namespace App\Filament\Resources\Users\Widgets;

use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UserRetentionStats extends StatsOverviewWidget
{
    protected ?string $heading = 'Gebruikersretentie';

    protected function getStats(): array
    {
        $stats = self::calculateStats();

        return [
            Stat::make('Actief laatste 7 dagen', (string) $stats['active_last_7_days'])
                ->description($stats['active_last_7_days_rate'] . '% van totaal'),

            Stat::make('Actief laatste 30 dagen', (string) $stats['active_last_30_days'])
                ->description($stats['active_last_30_days_rate'] . '% van totaal'),

            Stat::make('Terugkerende gebruikers', (string) $stats['returning_users'])
                ->description('Meer dan alleen eerste login'),
        ];
    }

    public static function calculateStats(): array
    {
        $now = CarbonImmutable::now();
        $sevenDaysAgo = $now->subDays(7);
        $thirtyDaysAgo = $now->subDays(30);

        $totalUsers = User::query()->count();

        $activeLast7Days = User::query()
            ->whereNotNull('last_login_at')
            ->where('last_login_at', '>=', $sevenDaysAgo)
            ->count();

        $activeLast30Days = User::query()
            ->whereNotNull('last_login_at')
            ->where('last_login_at', '>=', $thirtyDaysAgo)
            ->count();

        $returningUsers = User::query()
            ->whereNotNull('first_login_at')
            ->whereNotNull('last_login_at')
            ->whereColumn('last_login_at', '>', 'first_login_at')
            ->count();

        return [
            'active_last_7_days' => $activeLast7Days,
            'active_last_7_days_rate' => $totalUsers > 0
                ? round(($activeLast7Days / $totalUsers) * 100, 1)
                : 0.0,
            'active_last_30_days' => $activeLast30Days,
            'active_last_30_days_rate' => $totalUsers > 0
                ? round(($activeLast30Days / $totalUsers) * 100, 1)
                : 0.0,
            'returning_users' => $returningUsers,
        ];
    }
}
