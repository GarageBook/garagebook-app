<?php

namespace App\Console\Commands;

use App\Models\OutreachCampaign;
use App\Models\OutreachProspect;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ImportOutreachProspectsCommand extends Command
{
    protected $signature = 'garagebook:import-outreach-prospects
        {csv : Pad naar het CSV-bestand}
        {--campaign= : Campaign slug waarin de prospects moeten landen}
        {--dry-run : Toon alleen wat er zou gebeuren}
        {--update-existing : Vul bestaande prospects alleen veilig aan met lege velden}
        {--move-existing-to-campaign : Verplaats bestaande prospects naar de gekozen campaign}';

    protected $description = 'Importeer outreach prospects uit CSV en maak demo-links klaar.';

    public function handle(): int
    {
        $campaignSlug = (string) $this->option('campaign');
        $dryRun = (bool) $this->option('dry-run');
        $updateExisting = (bool) $this->option('update-existing');
        $moveExistingToCampaign = (bool) $this->option('move-existing-to-campaign');

        if (blank($campaignSlug)) {
            $this->error('Gebruik --campaign=slug om de outreach-campagne te kiezen.');

            return self::FAILURE;
        }

        $path = $this->resolveCsvPath((string) $this->argument('csv'));

        if (! is_file($path)) {
            $this->error('CSV-bestand niet gevonden: ' . $path);

            return self::FAILURE;
        }

        $campaign = $this->resolveCampaign($campaignSlug, $dryRun);
        $rows = $this->readCsv($path);

        $created = 0;
        $skippedExisting = 0;
        $updatedExisting = 0;
        $movedExisting = 0;
        $skippedMissingRequired = 0;
        $movedIntoTargetCampaign = 0;
        $movedIntoTargetCampaignWithoutSentMail = 0;

        $currentCampaignProspectCount = $campaign->exists
            ? $campaign->prospects()->count()
            : 0;
        $currentCampaignProspectsWithoutSentMail = $campaign->exists
            ? $campaign->prospects()
                ->whereDoesntHave('emailLogs', fn ($query) => $query->where('status', 'sent'))
                ->count()
            : 0;

        foreach ($rows as $row) {
            if (blank($row['company_name'])) {
                $skippedMissingRequired++;

                continue;
            }

            $matches = $this->findMatchingProspects($row);

            if ($matches->count() > 1) {
                $skippedExisting++;
                $this->line('Overgeslagen bestaande prospect met meerdere matches: ' . $this->describeRow($row));

                continue;
            }

            /** @var ?OutreachProspect $prospect */
            $prospect = $matches->first();

            if ($prospect instanceof OutreachProspect) {
                if (! $updateExisting && ! $moveExistingToCampaign) {
                    $skippedExisting++;

                    continue;
                }

                $changes = [];

                if ($updateExisting) {
                    $changes = $this->safeFillExistingProspect($prospect, $row);
                }

                $shouldMove = $moveExistingToCampaign
                    && (int) $prospect->outreach_campaign_id !== (int) $campaign->getKey();

                if ($shouldMove) {
                    $changes['outreach_campaign_id'] = $campaign->getKey();
                }

                if ($changes === []) {
                    $skippedExisting++;

                    continue;
                }

                if (! $dryRun) {
                    $prospect->forceFill($changes)->save();
                }

                if ($shouldMove) {
                    $movedExisting++;
                    $movedIntoTargetCampaign++;

                    if (! $this->prospectHasSentOutreachMail($prospect)) {
                        $movedIntoTargetCampaignWithoutSentMail++;
                    }
                } else {
                    $updatedExisting++;
                }

                continue;
            }

            if (! $dryRun) {
                OutreachProspect::query()->create($this->createPayload($campaign->getKey(), $row));
            }

            $created++;
        }

        if (! $dryRun && ! $campaign->exists) {
            $campaign = OutreachCampaign::query()->firstOrCreate(
                ['slug' => $campaignSlug],
                ['name' => Str::headline(str_replace('-', ' ', $campaignSlug))],
            );
        }

        $finalCampaignProspectCount = $campaign->exists
            ? $campaign->prospects()->count()
            : $currentCampaignProspectCount + $created + $movedIntoTargetCampaign;
        $finalCampaignProspectsWithoutSentMail = $campaign->exists
            ? $campaign->prospects()
                ->whereDoesntHave('emailLogs', fn ($query) => $query->where('status', 'sent'))
                ->count()
            : $currentCampaignProspectsWithoutSentMail + $created + $movedIntoTargetCampaignWithoutSentMail;

        $this->info($dryRun ? 'Dry-run: er zijn geen databasewijzigingen opgeslagen.' : 'Import voltooid.');
        $this->info('Aangemaakt: ' . $created);
        $this->info('Bestaande overgeslagen: ' . $skippedExisting);
        $this->info('Bestaande veilig aangevuld: ' . $updatedExisting);
        $this->info('Bestaande verplaatst: ' . $movedExisting);
        $this->info('Overgeslagen wegens ontbrekende verplichte velden: ' . $skippedMissingRequired);
        $this->info('Totaal prospects in gekozen campaign: ' . $finalCampaignProspectCount);
        $this->info('Prospects in gekozen campaign zonder sent outreach-mail: ' . $finalCampaignProspectsWithoutSentMail);

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

    /**
     * @return Collection<int, OutreachProspect>
     */
    private function findMatchingProspects(array $row): Collection
    {
        $normalizedWebsite = $this->normalizeWebsite($row['website']);
        $normalizedCompanyName = $this->normalizeComparableValue($row['company_name']);
        $normalizedEmail = $this->normalizeEmail($row['email']);

        return OutreachProspect::query()
            ->get()
            ->filter(function (OutreachProspect $prospect) use ($normalizedWebsite, $normalizedCompanyName, $normalizedEmail): bool {
                $matches = [];

                if ($normalizedWebsite !== null) {
                    $matches[] = $this->normalizeWebsite($prospect->website) === $normalizedWebsite;
                }

                if ($normalizedCompanyName !== null) {
                    $matches[] = $this->normalizeComparableValue($prospect->company_name) === $normalizedCompanyName;
                }

                if ($normalizedEmail !== null) {
                    $matches[] = $this->normalizeEmail($prospect->email) === $normalizedEmail;
                }

                return in_array(true, $matches, true);
            })
            ->values();
    }

    private function resolveCampaign(string $campaignSlug, bool $dryRun): OutreachCampaign
    {
        if ($dryRun) {
            $existingCampaign = OutreachCampaign::query()->where('slug', $campaignSlug)->first();

            if ($existingCampaign instanceof OutreachCampaign) {
                return $existingCampaign;
            }

            return new OutreachCampaign([
                'slug' => $campaignSlug,
                'name' => Str::headline(str_replace('-', ' ', $campaignSlug)),
            ]);
        }

        return OutreachCampaign::query()->firstOrCreate(
            ['slug' => $campaignSlug],
            ['name' => Str::headline(str_replace('-', ' ', $campaignSlug))],
        );
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private function createPayload(int $campaignId, array $row): array
    {
        return [
            'outreach_campaign_id' => $campaignId,
            'company_name' => $row['company_name'],
            'contact_name' => $row['contact_name'],
            'email' => $row['email'],
            'website' => $row['website'],
            'city' => $row['city'],
            'notes' => $row['notes'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function safeFillExistingProspect(OutreachProspect $prospect, array $row): array
    {
        $updates = [];

        foreach (['contact_name', 'email', 'website', 'city', 'notes'] as $field) {
            $incoming = trim((string) ($row[$field] ?? ''));

            if (blank($incoming) || filled($prospect->{$field})) {
                continue;
            }

            $updates[$field] = $incoming;
        }

        return $updates;
    }

    private function prospectHasSentOutreachMail(OutreachProspect $prospect): bool
    {
        return $prospect->emailLogs()
            ->where('status', 'sent')
            ->exists();
    }

    private function normalizeComparableValue(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : Str::lower($value);
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

    private function normalizeEmail(?string $email): ?string
    {
        $value = trim((string) $email);

        return $value === '' ? null : Str::lower($value);
    }

    /**
     * @param  array<string, string>  $row
     */
    private function describeRow(array $row): string
    {
        return implode(' | ', array_filter([
            'company_name=' . ($row['company_name'] ?? ''),
            'website=' . ($row['website'] ?? ''),
            'email=' . ($row['email'] ?? ''),
        ], fn (string $value): bool => ! str_ends_with($value, '=')));
    }
}
