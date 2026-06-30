<?php

namespace App\Services\Growth;

use App\Models\GrowthProspect;
use App\Services\Growth\Community2026AttentionCrawler;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\File;

class Community2026AttentionReviewService
{
    public function __construct(
        private readonly Community2026CleanupService $cleanup,
        private readonly Community2026AttentionCrawler $crawler,
    ) {}

    /**
     * @return array<string, int>
     */
    public function exportCsv(string $path, int $limit = 20): array
    {
        $records = $this->cleanup->attentionRecords($limit);
        $rows = [];
        $summary = [
            'attention_records' => $records->count(),
            'suggested_email_found' => 0,
            'contact_url_found' => 0,
            'possible_invalid' => 0,
        ];

        foreach ($records as $record) {
            $crawl = $this->crawler->crawl($record);
            $suggestedEmail = $crawl['suggested_email'];
            $contactUrl = $crawl['contact_url'];

            if ($suggestedEmail !== null) {
                $summary['suggested_email_found']++;
            }

            if ($contactUrl !== null) {
                $summary['contact_url_found']++;
            }

            if ($record->email_status === GrowthProspect::EMAIL_STATUS_INVALID && filter_var(trim((string) $record->email), FILTER_VALIDATE_EMAIL) !== false) {
                $summary['possible_invalid']++;
            }

            $rows[] = [
                'name' => (string) $record->name,
                'website' => (string) ($record->website ?? ''),
                'current_email' => (string) ($record->email ?? ''),
                'email_status' => (string) ($record->email_status ?? ''),
                'lifecycle_status' => (string) ($record->lifecycle_status ?? ''),
                'skip_reason' => (string) ($record->skip_reason ?? ''),
                'suggested_email' => $suggestedEmail ?? '',
                'contact_url' => $contactUrl ?? '',
                'notes' => $this->composeNotes($record, $crawl['notes'] ?? null),
            ];
        }

        $this->writeCsv($rows, $path);

        return $summary;
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     */
    private function writeCsv(array $rows, string $path): void
    {
        $path = $this->resolvePath($path);
        File::ensureDirectoryExists(dirname($path));

        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new \RuntimeException('Kan attention CSV niet schrijven: '.$path);
        }

        fputcsv($handle, ['name', 'website', 'current_email', 'email_status', 'lifecycle_status', 'skip_reason', 'suggested_email', 'contact_url', 'notes']);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['name'],
                $row['website'],
                $row['current_email'],
                $row['email_status'],
                $row['lifecycle_status'],
                $row['skip_reason'],
                $row['suggested_email'],
                $row['contact_url'],
                $row['notes'],
            ]);
        }

        fclose($handle);
    }

    private function composeNotes(GrowthProspect $record, ?string $crawlNotes): string
    {
        $notes = array_filter([
            trim((string) $record->notes),
            trim((string) $crawlNotes),
        ]);

        return implode(' | ', array_unique($notes));
    }

    private function resolvePath(string $path): string
    {
        if ($path === '') {
            return base_path('storage/app/imports/community2026_attention.csv');
        }

        if (str_starts_with($path, '/')) {
            return $path;
        }

        if (preg_match('/^[A-Za-z]:[\\/]/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }
}
