<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportNeverLoggedInUsersCommand extends Command
{
    private const LOGIN_TRACKING_STARTED_AT = '2026-04-22 00:00:00';

    protected $signature = 'users:export-never-logged-in
        {--output= : Pad voor de CSV-export}
        {--include-pre-tracking : Neem ook users van voor 22 april 2026 mee}';

    protected $description = 'Exporteer users die nog niet hebben ingelogd als CSV voor MailerLite.';

    public function handle(): int
    {
        $path = $this->outputPath();

        $query = User::query()
            ->whereNull('first_login_at')
            ->orderBy('created_at');

        if (! $this->option('include-pre-tracking')) {
            $query->where('created_at', '>=', self::LOGIN_TRACKING_STARTED_AT);
        }

        $rows = $query
            ->get(['name', 'email'])
            ->map(fn (User $user): array => [
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->all();

        $this->writeCsv($path, $rows);

        $this->info('CSV opgeslagen in: ' . $path);
        $this->info('Aantal users geëxporteerd: ' . count($rows));

        if (! $this->option('include-pre-tracking')) {
            $this->warn('Alleen users aangemaakt vanaf 2026-04-22 00:00:00 zijn meegenomen.');
        }

        return self::SUCCESS;
    }

    private function outputPath(): string
    {
        $customPath = $this->option('output');

        if (filled($customPath)) {
            return $customPath;
        }

        return storage_path('app/mailerlite-never-logged-in-users-' . now()->format('Ymd-His') . '.csv');
    }

    private function writeCsv(string $path, array $rows): void
    {
        File::ensureDirectoryExists(dirname($path));

        $handle = fopen($path, 'w');
        fputcsv($handle, ['name', 'email']);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
    }
}
