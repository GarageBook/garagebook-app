<?php

namespace App\Filament\Resources\Users\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UserActivationStats extends StatsOverviewWidget
{
    protected ?string $heading = 'Gebruikersactivatie';

    protected function getStats(): array
    {
        $stats = self::calculateStats();

        return [
            Stat::make('Totaal gebruikers', (string) $stats['total_users'])
                ->description('Alle accounts in het platform'),

            Stat::make('Ooit ingelogd', (string) $stats['activated_users'])
                ->description($stats['activation_rate'] . '% activatie'),

            Stat::make('Nog niet ingelogd', (string) $stats['inactive_users'])
                ->description('Opvolging nodig'),
        ];
    }

    public static function calculateStats(): array
    {
        $totalUsers = User::query()->count();
        $activatedUsers = User::query()->whereNotNull('first_login_at')->count();
        $inactiveUsers = $totalUsers - $activatedUsers;

        return [
            'total_users' => $totalUsers,
            'activated_users' => $activatedUsers,
            'inactive_users' => $inactiveUsers,
            'activation_rate' => $totalUsers > 0
                ? round(($activatedUsers / $totalUsers) * 100, 1)
                : 0.0,
        ];
    }
}
