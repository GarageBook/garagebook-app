<?php

namespace App\Console\Commands;

use App\Services\Growth\Motorclubs\MotorclubImportResult;
use App\Services\Growth\Motorclubs\MotorclubImportService;
use Illuminate\Console\Command;

class ImportMotorclubGrowthProspectsCommand extends Command
{
    protected $signature = 'garagebook:growth:import-motorclubs
        {--file=docs/imports/motorclubs.csv : CSV-bronbestand}
        {--markdown=docs/prospects/motorclubs.md : Markdown-bronbestand voor controle en verrijking}
        {--limit= : Maximaal aantal records}
        {--campaign= : Alleen club2026 of classic2026 verwerken}
        {--dry-run : Niets schrijven, alleen rapporteren}
        {--force : Bestaande prospects expliciet veilig bijwerken}';

    protected $description = 'Importeer de gerichte motorclublijst veilig naar growth_prospects zonder outreach te queueën of te versturen.';

    public function handle(MotorclubImportService $importer): int
    {
        $file = $this->resolvePath((string) $this->option('file'));
        $markdown = $this->resolvePath((string) $this->option('markdown'));
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;
        $dryRun = (bool) $this->option('dry-run') || ! (bool) $this->option('force');

        if (! is_file($file)) {
            $this->error('Inputbestand niet gevonden: '.$file);

            return self::FAILURE;
        }

        if (! is_file($markdown)) {
            $this->error('Markdown controlebestand niet gevonden: '.$markdown);

            return self::FAILURE;
        }

        if (! $dryRun && ! $this->confirm('Dit schrijft motorclubprospects naar de lokale database. Er wordt niets gemaild. Doorgaan?', false)) {
            $this->warn('Import afgebroken.');

            return self::FAILURE;
        }

        $result = $importer->import($file, [
            'dry_run' => $dryRun,
            'limit' => $limit,
            'campaign' => $this->option('campaign') !== null ? (string) $this->option('campaign') : null,
            'force' => (bool) $this->option('force'),
            'markdown_file' => $markdown,
        ]);

        if ($result->errors !== []) {
            foreach ($result->errors as $error) {
                $this->error($error);
            }

            $this->line('0 outreachberichten gequeued');
            $this->line('0 outreachberichten verzonden');

            return self::FAILURE;
        }

        $this->renderSummary($result, $dryRun);

        return self::SUCCESS;
    }

    private function renderSummary(MotorclubImportResult $result, bool $dryRun): void
    {
        $this->info($dryRun ? 'Motorclubimport dry-run voltooid.' : 'Motorclubimport voltooid.');
        $this->line('Modus: '.($dryRun ? 'dry-run' : 'write'));
        $this->newLine();

        foreach ($result->summary as $label => $count) {
            $this->line(str_replace('_', ' ', $label).': '.$count);
        }

        $this->newLine();
        $this->line('Verdeling per campagne:');
        foreach ($result->campaigns as $campaign => $count) {
            $this->line($campaign.': '.$count);
        }

        $this->newLine();
        $this->line('Verdeling per subtype:');
        foreach ($result->subtypes as $subtype => $count) {
            $this->line($subtype.': '.$count);
        }

        if ($result->fieldChanges !== []) {
            $this->newLine();
            $this->line('Veldwijzigingen:');
            foreach ($result->fieldChanges as $field => $count) {
                $this->line($field.': '.$count);
            }
        }

        if ($result->sourceInconsistencies !== []) {
            $this->newLine();
            $this->warn('Broninconsistenties tussen CSV en Markdown:');
            $this->table(
                ['Naam', 'Veld', 'CSV', 'Markdown'],
                array_map(static fn (array $row): array => [
                    $row['name'],
                    $row['field'],
                    $row['csv'],
                    $row['markdown'],
                ], $result->sourceInconsistencies),
            );
        }

        $attention = array_values(array_filter($result->records, static fn (array $record): bool => $record['reasons'] !== [] || ($record['changes'] ?? []) !== [] || in_array($record['action'], ['existing', 'duplicates', 'invalid', 'excluded'], true)));

        if ($attention !== []) {
            $this->newLine();
            $this->warn('Records voor controle:');
            $this->table(
                ['Naam', 'Campagne', 'Subtype', 'Actie', 'Status', 'Redenen', 'Wijzigingen'],
                array_map(static fn (array $record): array => [
                    $record['name'],
                    $record['campaign'],
                    $record['subtype'],
                    $record['action'],
                    $record['status'],
                    implode(', ', $record['reasons']),
                    implode(', ', array_map(
                        static fn (string $field, array $change): string => $field.': '.(is_array($change['from'] ?? null) ? json_encode($change['from']) : (string) ($change['from'] ?? 'null')).' -> '.(is_array($change['to'] ?? null) ? json_encode($change['to']) : (string) ($change['to'] ?? 'null')),
                        array_keys($record['changes'] ?? []),
                        $record['changes'] ?? [],
                    )),
                ], $attention),
            );
        }

        $this->newLine();
        $this->info('Verwachte importuitkomst:');
        $this->line($result->summary['read'].' gelezen');
        $this->line($result->summary['public_email'].' technisch contacteerbaar');
        $this->line(($result->summary['read'] - $result->summary['public_email']).' manual review/enrichment signalen');
        foreach ($result->campaigns as $campaign => $count) {
            $this->line($count.' '.$campaign);
        }
        $this->line($result->summary['queued'].' outreachberichten gequeued');
        $this->line($result->summary['sent'].' outreachberichten verzonden');
    }

    private function resolvePath(string $path): string
    {
        if (is_file($path)) {
            return $path;
        }

        $basePath = base_path($path);

        return is_file($basePath) ? $basePath : $path;
    }
}
