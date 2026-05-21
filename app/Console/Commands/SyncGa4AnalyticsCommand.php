<?php

namespace App\Console\Commands;

use App\Models\AnalyticsDailySummary;
use App\Models\AnalyticsTopPage;
use App\Services\Analytics\Ga4AnalyticsService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SyncGa4AnalyticsCommand extends Command
{
    protected $signature = 'garagebook:sync-ga4-analytics
        {--from= : Startdatum in YYYY-MM-DD}
        {--to= : Einddatum in YYYY-MM-DD}
        {--debug-response : Toon property ID, datumrange en response-aantallen zonder secrets}';

    protected $description = 'Synchroniseer GA4 samenvattingen en top pages naar de lokale database.';

    public function handle(): int
    {
        $service = app(Ga4AnalyticsService::class);

        if (! $service->isConfigured()) {
            $this->warn(($service->configurationError() ?? 'GA4 configuratie ontbreekt.') . ' Geen data gesynchroniseerd.');

            return self::SUCCESS;
        }

        [$from, $to] = $this->resolveDateRange(defaultOffsetDays: 1);

        if ($this->option('debug-response')) {
            $this->line('GA4 debug response');
            $this->line('Property ID: ' . (string) config('services.google_analytics.property_id'));
            $this->line('Datumrange: ' . $from->toDateString() . ' t/m ' . $to->toDateString());
        }

        $syncedDays = 0;

        foreach ($this->datesBetween($from, $to) as $date) {
            $dailySummary = $service->fetchDailySummary($date);

            if ($this->option('debug-response')) {
                $this->line('Daily summary rows voor ' . $date->toDateString() . ': ' . ($dailySummary !== null ? '1' : '0'));
            }

            if ($dailySummary !== null) {
                AnalyticsDailySummary::query()->upsert(
                    [$dailySummary],
                    ['date'],
                    ['users', 'sessions', 'screen_page_views', 'event_count', 'conversions', 'updated_at'],
                );
            }

            $topPages = $service->fetchTopPages($date, limit: 25);

            if ($this->option('debug-response')) {
                $this->line('Top pages rows voor ' . $date->toDateString() . ': ' . count($topPages));
            }

            if ($topPages !== []) {
                AnalyticsTopPage::query()->upsert(
                    $topPages,
                    ['date', 'page_path'],
                    ['page_title', 'views', 'users', 'updated_at'],
                );
            }

            $syncedDays++;
        }

        $this->info("GA4 analytics gesynchroniseerd voor {$syncedDays} dag(en).");

        return self::SUCCESS;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolveDateRange(int $defaultOffsetDays): array
    {
        $defaultDate = CarbonImmutable::yesterday()->subDays($defaultOffsetDays - 1);

        $from = $this->option('from')
            ? CarbonImmutable::parse((string) $this->option('from'))->startOfDay()
            : $defaultDate->startOfDay();

        $to = $this->option('to')
            ? CarbonImmutable::parse((string) $this->option('to'))->startOfDay()
            : $from;

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    /**
     * @return \Generator<int, CarbonImmutable>
     */
    private function datesBetween(CarbonImmutable $from, CarbonImmutable $to): \Generator
    {
        for ($date = $from; $date->lte($to); $date = $date->addDay()) {
            yield $date;
        }
    }
}
