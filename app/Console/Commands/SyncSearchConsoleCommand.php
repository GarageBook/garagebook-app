<?php

namespace App\Console\Commands;

use App\Models\SearchConsoleDailySummary;
use App\Models\SearchConsolePage;
use App\Models\SearchConsoleQuery;
use App\Services\Analytics\SearchConsoleService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SyncSearchConsoleCommand extends Command
{
    protected $signature = 'garagebook:sync-search-console
        {--from= : Startdatum in YYYY-MM-DD}
        {--to= : Einddatum in YYYY-MM-DD}';

    protected $description = 'Synchroniseer Search Console samenvattingen, queries en pages naar de lokale database.';

    public function handle(): int
    {
        $service = app(SearchConsoleService::class);

        if (! $service->isConfigured()) {
            $this->warn('Search Console credentials of site URL ontbreken. Geen data gesynchroniseerd.');

            return self::SUCCESS;
        }

        [$from, $to] = $this->resolveDateRange();

        $syncedDays = 0;

        foreach ($this->datesBetween($from, $to) as $date) {
            $dailySummary = $service->fetchDailySummary($date);

            if ($dailySummary !== null) {
                SearchConsoleDailySummary::query()->upsert(
                    [$dailySummary],
                    ['date'],
                    ['clicks', 'impressions', 'ctr', 'position', 'updated_at'],
                );
            }

            $topQueries = $service->fetchTopQueries($date, limit: 25);

            if ($topQueries !== []) {
                SearchConsoleQuery::query()->upsert(
                    $topQueries,
                    ['date', 'query'],
                    ['clicks', 'impressions', 'ctr', 'position', 'updated_at'],
                );
            }

            $topPages = $service->fetchTopPages($date, limit: 25);

            if ($topPages !== []) {
                SearchConsolePage::query()->upsert(
                    $topPages,
                    ['date', 'page'],
                    ['clicks', 'impressions', 'ctr', 'position', 'updated_at'],
                );
            }

            $syncedDays++;
        }

        $this->info("Search Console data gesynchroniseerd voor {$syncedDays} dag(en).");

        return self::SUCCESS;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolveDateRange(): array
    {
        $defaultDate = CarbonImmutable::today()->subDays(3);

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
