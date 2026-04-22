<?php

namespace App\Console\Commands;

use App\Services\Airtable\AirtableClient;
use App\Services\Airtable\AirtableUserDataImporter;
use App\Services\Airtable\AirtableUserImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ImportAllAirtableUsersCommand extends Command
{
    protected $signature = 'airtable:import-all-users
        {--force : Schrijf wijzigingen echt weg}
        {--with-related : Importeer ook gekoppelde voertuigen en onderhoud}
        {--passwords-output= : Pad voor CSV met tijdelijke wachtwoorden}';

    protected $description = 'Importeer alle users uit Airtable en genereer tijdelijke wachtwoorden voor nieuwe accounts.';

    public function handle(
        AirtableClient $client,
        AirtableUserImporter $userImporter,
        AirtableUserDataImporter $dataImporter
    ): int {
        $shouldPersist = (bool) $this->option('force');
        $withRelated = (bool) $this->option('with-related');

        if ($withRelated && ! $shouldPersist) {
            $this->error('--with-related vereist --force.');

            return self::FAILURE;
        }

        $records = $client->listUsers();
        $rows = [];
        $results = [];

        foreach ($records as $record) {
            $plainPassword = $shouldPersist ? $this->generateTemporaryPassword() : null;
            $result = $shouldPersist
                ? $userImporter->importRecord($record, $plainPassword)
                : $userImporter->previewByRecordId($record['id']);

            if ($shouldPersist && $withRelated) {
                $user = \App\Models\User::query()->findOrFail($result['user_id']);
                $result = array_merge($result, $dataImporter->importForUser($user));
            }

            $results[] = $result;

            if ($shouldPersist && ($result['action'] ?? null) === 'created') {
                $rows[] = [
                    'record_id' => $result['record_id'],
                    'name' => $result['name'],
                    'email' => $result['email'],
                    'temporary_password' => $result['plain_password'],
                ];
            }
        }

        foreach ($results as $result) {
            $this->line(sprintf(
                '%s | %s | %s | %s',
                $result['action'] ?? 'preview',
                $result['record_id'],
                $result['email'] ?? $result['matched_user_email'] ?? '',
                $result['name'] ?? ''
            ));
        }

        if (! $shouldPersist) {
            $this->warn('Dry run: geen wijzigingen opgeslagen. Gebruik --force om de bulkimport echt uit te voeren.');

            return self::SUCCESS;
        }

        $path = $this->passwordOutputPath();
        $this->writePasswordsCsv($path, $rows);
        $this->info('Tijdelijke wachtwoorden opgeslagen in: ' . $path);

        return self::SUCCESS;
    }

    private function passwordOutputPath(): string
    {
        $customPath = $this->option('passwords-output');

        if (filled($customPath)) {
            return $customPath;
        }

        return storage_path('app/airtable-user-passwords-' . now()->format('Ymd-His') . '.csv');
    }

    private function writePasswordsCsv(string $path, array $rows): void
    {
        File::ensureDirectoryExists(dirname($path));

        $handle = fopen($path, 'w');
        fputcsv($handle, ['record_id', 'name', 'email', 'temporary_password']);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
    }

    private function generateTemporaryPassword(): string
    {
        return 'GB-' . Str::upper(Str::random(4)) . '-' . Str::random(8) . '!';
    }
}
