<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Lifecycle\Rules\LifecycleRuleEngine;
use Illuminate\Console\Command;

class EvaluateLifecycleRulesCommand extends Command
{
    protected $signature = 'garagebook:lifecycle:evaluate-rules {--chunk=100 : Aantal users per batch} {--no-store : Alleen rapporteren, niets opslaan}';

    protected $description = 'Evalueert lifecycle rules in shadow mode zonder mails of queues te starten.';

    public function handle(LifecycleRuleEngine $engine): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $persist = ! (bool) $this->option('no-store');
        $processed = 0;
        $matched = 0;
        $winnerCounts = [];

        User::query()
            ->orderBy('id')
            ->chunkById($chunkSize, function ($users) use ($engine, $persist, &$processed, &$matched, &$winnerCounts): void {
                foreach ($users as $user) {
                    $result = $engine->evaluate($user, persist: $persist);
                    $processed++;

                    if ($result['winner'] !== null) {
                        $matched++;
                        $winnerCounts[$result['winner']->ruleName] = ($winnerCounts[$result['winner']->ruleName] ?? 0) + 1;
                    }
                }
            });

        $this->info('Lifecycle rule users verwerkt: '.$processed);
        $this->info('Lifecycle rule matches: '.$matched);
        $this->info('Shadow mode: actief');
        $this->info('Opslaan evaluaties: '.($persist ? 'ja' : 'nee'));

        foreach ($winnerCounts as $ruleName => $count) {
            $this->line($ruleName.': '.$count);
        }

        return self::SUCCESS;
    }
}
