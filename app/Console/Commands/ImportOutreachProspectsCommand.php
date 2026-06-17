<?php

namespace App\Console\Commands;

use App\Models\OutreachCampaign;
use App\Models\OutreachProspect;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportOutreachProspectsCommand extends Command
{
    protected $signature = 'garagebook:import-outreach-prospects
        {csv : Pad naar het CSV-bestand}
        {--campaign= : Campaign slug waarin de prospects moeten landen}';

    protected $description = 'Importeer outreach prospects uit CSV en maak demo-links klaar.';

    public function handle(): int
    {
        $campaignSlug = (string) $this->option('campaign');

        if (blank($campaignSlug)) {
            $this->error('Gebruik --campaign=slug om de outreach-campagne te kiezen.');

            return self::FAILURE;
        }

        $path = $this->resolveCsvPath((string) $this->argument('csv'));

        if (! is_file($path)) {
            $this->error('CSV-bestand niet gevonden: ' . $path);

            return self::FAILURE;
        }

        $campaign = OutreachCampaign::query()->firstOrCreate(
            ['slug' => $campaignSlug],
            ['name' => Str::headline(str_replace('-', ' ', $campaignSlug))],
        );

        $rows = $this->readCsv($path);
        $created = 0;
        $updated = 0;

        foreach ($rows as $row) {
            $prospect = $this->findExistingProspect($row);

            $payload = [
                'outreach_campaign_id' => $campaign->id,
                'company_name' => $row['company_name'],
                'contact_name' => $row['contact_name'],
                'email' => $row['email'],
                'website' => $row['website'],
                'city' => $row['city'],
                'notes' => $row['notes'],
            ];

            if ($prospect) {
                $prospect->fill($payload)->save();
                $updated++;
                continue;
            }

            OutreachProspect::query()->create($payload);
            $created++;
        }

        $this->info('Campagne: ' . $campaign->name . ' (' . $campaign->slug . ')');
        $this->info('Prospects aangemaakt: ' . $created);
        $this->info('Prospects bijgewerkt/hergebruikt: ' . $updated);

        return self::SUCCESS;
    }

    private function resolveCsvPath(string $path): string
    {
        if (is_file($path)) {
            return $path;
        }

        $basePath = base_path($path);

        if (is_file($basePath)) {
            return $basePath;
        }

        return $path;
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);

        if (! is_array($header)) {
            fclose($handle);
            return [];
        }

        $header = array_map(fn ($value) => trim((string) $value), $header);
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if ($data === [null] || $data === false) {
                continue;
            }

            $row = array_combine($header, array_pad($data, count($header), ''));

            if (! is_array($row)) {
                continue;
            }

            $rows[] = [
                'company_name' => trim((string) ($row['company_name'] ?? '')),
                'city' => trim((string) ($row['city'] ?? '')),
                'website' => trim((string) ($row['website'] ?? '')),
                'email' => trim((string) ($row['email'] ?? '')),
                'contact_name' => trim((string) ($row['contact_name'] ?? '')),
                'notes' => trim((string) ($row['notes'] ?? '')),
            ];
        }

        fclose($handle);

        return array_values(array_filter($rows, fn (array $row) => filled($row['company_name'])));
    }

    private function findExistingProspect(array $row): ?OutreachProspect
    {
        $normalizedWebsite = $this->normalizeWebsite($row['website']);

        if (filled($normalizedWebsite)) {
            $prospect = OutreachProspect::query()->get()->first(function (OutreachProspect $prospect) use ($normalizedWebsite): bool {
                return $this->normalizeWebsite($prospect->website) === $normalizedWebsite;
            });

            if ($prospect) {
                return $prospect;
            }
        }

        return OutreachProspect::query()
            ->whereRaw('LOWER(company_name) = ?', [Str::lower($row['company_name'])])
            ->first();
    }

    private function normalizeWebsite(?string $website): ?string
    {
        $value = trim((string) $website);

        if ($value === '') {
            return null;
        }

        $value = Str::lower($value);
        $value = preg_replace('#^https?://#', '', $value) ?: $value;
        $value = preg_replace('#^www\.#', '', $value) ?: $value;

        return rtrim($value, '/');
    }
}
