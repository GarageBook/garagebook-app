<?php

namespace App\Console\Commands;

use App\Models\WeeklyReportSnapshot;
use App\Services\Growth\WeeklySnapshotParser;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ImportWeeklySnapshotCommand extends Command
{
    protected $signature = 'garagebook:import-weekly-snapshot
        {file? : Pad naar een tekstbestand; zonder bestand wordt stdin gelezen}
        {--date= : Rapportdatum in YYYY-MM-DD}';

    protected $description = 'Preview en importeer geaggregeerde historische weekly-reportmetrics.';

    public function handle(WeeklySnapshotParser $parser): int
    {
        $file = $this->argument('file');

        if ($file !== null && ! is_file((string) $file)) {
            $this->error('Bestand niet gevonden: '.$file);

            return self::FAILURE;
        }

        if ($file === null) {
            $this->line('Plak het rapport regel voor regel; sluit af met een lege regel.');
        }

        $rawText = $file !== null
            ? (string) file_get_contents((string) $file)
            : $this->readMultilineInput();
        $rawText = trim($rawText);

        if ($rawText === '') {
            $this->error('Geen rapporttekst ontvangen.');

            return self::FAILURE;
        }

        $date = $this->reportDate();

        if ($date === null) {
            return self::FAILURE;
        }

        $hash = hash('sha256', $rawText);

        if (WeeklyReportSnapshot::query()->where('raw_text_hash', $hash)->exists()) {
            $this->error('Dit rapport is al geïmporteerd; bestaande snapshot wordt niet overschreven.');

            return self::FAILURE;
        }

        $parsed = $parser->parse($rawText);
        $this->info('Preview weekly snapshot '.$date);
        $this->table(
            ['Metric', 'Waarde', 'Teller', 'Noemer', 'Eenheid'],
            collect($parsed['metrics'])->map(fn (array $metric): array => [
                $metric['metric_key'],
                $metric['value_numeric'],
                $metric['numerator'] ?? '—',
                $metric['denominator'] ?? '—',
                $metric['unit'],
            ])->all(),
        );

        foreach ($parsed['warnings'] as $warning) {
            $this->warn($warning);
        }

        if ($parsed['metrics'] === []) {
            $this->error('Geen bekende metrics gevonden; niets opgeslagen.');

            return self::FAILURE;
        }

        if (! $this->confirm('Snapshot met deze metrics opslaan?')) {
            $this->warn('Import geannuleerd; niets opgeslagen.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($date, $file, $rawText, $hash, $parsed): void {
            $snapshot = WeeklyReportSnapshot::query()->create([
                'report_date' => $date,
                'source_type' => $file === null ? 'stdin' : 'file',
                'definition_version' => WeeklySnapshotParser::DEFINITION_VERSION,
                'raw_text' => $rawText,
                'raw_text_hash' => $hash,
                'parser_version' => WeeklySnapshotParser::PARSER_VERSION,
                'imported_at' => now(),
                'imported_by_user_id' => null,
                'parse_status' => $parsed['status'],
                'warnings' => $parsed['warnings'],
            ]);

            $snapshot->metrics()->createMany($parsed['metrics']);
        });

        $this->info('Weekly snapshot opgeslagen.');

        return self::SUCCESS;
    }

    private function reportDate(): ?string
    {
        $value = (string) ($this->option('date') ?: now('Europe/Amsterdam')->toDateString());

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value, 'Europe/Amsterdam');
        } catch (\Throwable) {
            $date = null;
        }

        if ($date === null || $date->format('Y-m-d') !== $value) {
            $this->error('Ongeldige --date; gebruik YYYY-MM-DD.');

            return null;
        }

        return $value;
    }

    private function readMultilineInput(): string
    {
        $lines = [];

        while (true) {
            $line = $this->ask('Rapportregel (lege regel = klaar)');

            if ($line === null || $line === '') {
                break;
            }

            $lines[] = $line;
        }

        return implode(PHP_EOL, $lines);
    }
}
