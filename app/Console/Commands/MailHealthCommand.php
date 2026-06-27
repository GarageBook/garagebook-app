<?php

namespace App\Console\Commands;

use App\Support\LifecycleMailHealth;
use Illuminate\Console\Command;

class MailHealthCommand extends Command
{
    protected $signature = 'garagebook:mail-health';

    protected $description = 'Controleert of de lifecycle-mailconfig veilig kan verzenden.';

    public function handle(LifecycleMailHealth $health): int
    {
        $report = $health->report();

        $this->line('Lifecycle mail health');
        $this->line('Mailer: '.$report['mailer']);
        $this->line('Transport: '.$report['transport']);
        $this->line('Release path: '.($report['release_path'] ?: '-'));
        $this->newLine();

        foreach ($report['checks'] as $check) {
            $prefix = match ($check['severity']) {
                'error' => $check['ok'] ? '[ok]' : '[fail]',
                'warning' => $check['ok'] ? '[ok]' : '[warn]',
                default => '[ok]',
            };

            $this->line(sprintf('%s %s: %s', $prefix, $check['label'], $check['value']));
        }

        if (! $report['healthy']) {
            $this->newLine();
            $this->error('Lifecycle mail health failed.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Lifecycle mail health ok.');

        return self::SUCCESS;
    }
}
