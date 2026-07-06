<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Lifecycle\Mail\LifecycleMailAdapter;
use App\Services\Lifecycle\Rules\LifecycleRuleEngine;
use Illuminate\Console\Command;

class EvaluateLifecycleRulesCommand extends Command
{
    protected $signature = 'garagebook:lifecycle:evaluate-rules
        {--chunk=100 : Aantal users per batch}
        {--no-store : Alleen rapporteren, niets opslaan}
        {--preview-mail : Toon welke lifecycle mail gekozen zou worden zonder te queueën of loggen}';

    protected $description = 'Evalueert lifecycle rules in shadow mode zonder mails of queues te starten.';

    public function handle(LifecycleRuleEngine $engine, LifecycleMailAdapter $mailAdapter): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $persist = ! (bool) $this->option('no-store');
        $previewMail = (bool) $this->option('preview-mail');
        $processed = 0;
        $matched = 0;
        $winnerCounts = [];

        User::query()
            ->orderBy('id')
            ->chunkById($chunkSize, function ($users) use ($engine, $mailAdapter, $persist, $previewMail, &$processed, &$matched, &$winnerCounts): void {
                foreach ($users as $user) {
                    $result = $engine->evaluate($user, persist: $persist);
                    $processed++;

                    if ($result['winner'] !== null) {
                        $matched++;
                        $winnerCounts[$result['winner']->ruleName] = ($winnerCounts[$result['winner']->ruleName] ?? 0) + 1;

                        if ($previewMail) {
                            $preview = $mailAdapter->preview($user, $result['winner']);

                            $this->line(sprintf(
                                'Mail preview user=%s rule=%s template=%s eligible=%s reason=%s',
                                $preview['user_id'],
                                $preview['rule_name'],
                                $preview['template_key'] ?? '-',
                                $preview['eligible'] ? 'ja' : 'nee',
                                $preview['reason'],
                            ));
                        }
                    }
                }
            });

        $this->info('Lifecycle rule users verwerkt: '.$processed);
        $this->info('Lifecycle rule matches: '.$matched);
        $this->info('Shadow mode: actief');
        $this->info('Opslaan evaluaties: '.($persist ? 'ja' : 'nee'));
        $this->info('Mail preview: '.($previewMail ? 'ja' : 'nee'));

        foreach ($winnerCounts as $ruleName => $count) {
            $this->line($ruleName.': '.$count);
        }

        return self::SUCCESS;
    }
}
