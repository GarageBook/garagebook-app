<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class RunDisasterRecoveryBackupCommand extends Command
{
    protected $signature = 'backup:run-disaster-recovery
        {--keep= : Override the number of remote restore points to keep}';

    protected $description = 'Create and upload a disaster recovery backup to the configured offsite storage.';

    public function handle(): int
    {
        $diskName = 'backups';
        $diskConfig = config("filesystems.disks.{$diskName}", []);

        if (($diskConfig['driver'] ?? null) !== 's3' && app()->environment('production')) {
            $this->error('The backups disk is not configured for S3-compatible offsite storage.');

            return self::FAILURE;
        }

        $bucket = (string) ($diskConfig['bucket'] ?? '');
        $endpoint = (string) ($diskConfig['endpoint'] ?? '');

        if ($bucket === '' || $endpoint === '') {
            $this->error('Missing backup bucket or endpoint configuration for the backups disk.');

            return self::FAILURE;
        }

        $retention = max(1, (int) ($this->option('keep') ?: config('backups.retention_days', 7)));
        $remotePrefix = trim((string) config('backups.remote_prefix', 'daily'), '/');
        $timestamp = now()->utc()->format('Y-m-d\THis\Z');
        $restorePointPath = "{$remotePrefix}/{$timestamp}";
        $tempDirectory = rtrim(sys_get_temp_dir(), '/')."/garagebook-backup-{$timestamp}";

        File::ensureDirectoryExists($tempDirectory);

        try {
            $artifacts = [];

            $artifacts[] = $this->createDatabaseBackup($tempDirectory, $timestamp);
            $artifacts[] = $this->copyEnvFile($tempDirectory, $timestamp);
            $artifacts[] = $this->createTarGzArchive(
                $tempDirectory,
                "storage-{$timestamp}.tar.gz",
                [storage_path()],
                'app storage'
            );

            $extraPaths = config('backups.extra_paths', []);

            if ($extraPaths !== []) {
                $artifacts[] = $this->createTarGzArchive(
                    $tempDirectory,
                    "server-config-{$timestamp}.tar.gz",
                    $extraPaths,
                    'extra server paths'
                );
            }

            $manifestPath = $this->createManifest($tempDirectory, $timestamp, $artifacts, $retention, $remotePrefix, $extraPaths);
            $artifacts[] = $manifestPath;

            $disk = Storage::disk($diskName);

            foreach ($artifacts as $artifact) {
                $remotePath = "{$restorePointPath}/".basename($artifact);
                $stream = fopen($artifact, 'rb');

                if ($stream === false) {
                    throw new \RuntimeException("Could not open artifact for upload: {$artifact}");
                }

                try {
                    $disk->put($remotePath, $stream);
                } finally {
                    fclose($stream);
                }

                $this->components->info("Uploaded {$remotePath}");
            }

            $this->pruneOldRestorePoints($diskName, $remotePrefix, $retention);
            $this->components->info("Disaster recovery backup completed for {$timestamp}.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            File::deleteDirectory($tempDirectory);
        }
    }

    private function createDatabaseBackup(string $tempDirectory, string $timestamp): string
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        return match ($driver) {
            'sqlite' => $this->createSqliteBackup($connection, $tempDirectory, $timestamp),
            'mysql', 'mariadb' => $this->createMySqlBackup($connection, $tempDirectory, $timestamp),
            default => throw new \RuntimeException("The backup command does not support the active driver: {$driver}"),
        };
    }

    private function createSqliteBackup(string $connection, string $tempDirectory, string $timestamp): string
    {
        $databasePath = (string) config("database.connections.{$connection}.database");

        if ($databasePath === '' || ! is_file($databasePath)) {
            throw new \RuntimeException("Could not find the sqlite database at {$databasePath}");
        }

        $backupPath = "{$tempDirectory}/database-{$timestamp}.sqlite";
        $quotedBackupPath = str_replace("'", "''", $backupPath);

        DB::purge($connection);
        DB::connection($connection)->unprepared("VACUUM INTO '{$quotedBackupPath}'");
        DB::disconnect($connection);

        if (! is_file($backupPath)) {
            throw new \RuntimeException('The sqlite backup file was not created.');
        }

        return $backupPath;
    }

    private function createMySqlBackup(string $connection, string $tempDirectory, string $timestamp): string
    {
        $config = config("database.connections.{$connection}");
        $database = (string) ($config['database'] ?? '');

        if ($database === '') {
            throw new \RuntimeException('Could not determine the MySQL database name for the backup.');
        }

        $backupPath = "{$tempDirectory}/database-{$timestamp}.sql";
        $command = [
            (string) config('backups.mysql_dump_binary', 'mysqldump'),
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--no-tablespaces',
            '--result-file='.$backupPath,
        ];

        $socket = (string) ($config['unix_socket'] ?? '');

        if ($socket !== '') {
            $command[] = '--socket='.$socket;
        } else {
            $host = (string) ($config['host'] ?? '127.0.0.1');
            $port = (string) ($config['port'] ?? '3306');
            $command[] = '--host='.$host;
            $command[] = '--port='.$port;
        }

        $username = (string) ($config['username'] ?? '');

        if ($username !== '') {
            $command[] = '--user='.$username;
        }

        $command[] = $database;

        $environment = null;
        $password = (string) ($config['password'] ?? '');

        if ($password !== '') {
            $environment = array_merge($_ENV, [
                'MYSQL_PWD' => $password,
            ]);
        }

        $process = new Process($command, base_path(), $environment, null, 300);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Could not create the MySQL backup: '.$process->getErrorOutput());
        }

        if (! is_file($backupPath) || filesize($backupPath) === 0) {
            throw new \RuntimeException('The MySQL backup file was not created.');
        }

        return $backupPath;
    }

    private function copyEnvFile(string $tempDirectory, string $timestamp): string
    {
        $source = base_path('.env');

        if (! is_file($source)) {
            throw new \RuntimeException('The .env file is missing and cannot be backed up.');
        }

        $destination = "{$tempDirectory}/env-{$timestamp}.backup";

        if (! copy($source, $destination)) {
            throw new \RuntimeException('Could not copy the .env file into the backup workspace.');
        }

        return $destination;
    }

    /**
     * @param  list<string>  $absolutePaths
     */
    private function createTarGzArchive(
        string $tempDirectory,
        string $archiveFilename,
        array $absolutePaths,
        string $label
    ): string {
        $archivePath = "{$tempDirectory}/{$archiveFilename}";
        $relativePaths = [];

        foreach ($absolutePaths as $absolutePath) {
            if (! str_starts_with($absolutePath, '/')) {
                throw new \RuntimeException("Backup path must be absolute: {$absolutePath}");
            }

            if (! file_exists($absolutePath)) {
                throw new \RuntimeException("Backup path does not exist: {$absolutePath}");
            }

            if (! is_readable($absolutePath)) {
                throw new \RuntimeException("Backup path is not readable: {$absolutePath}");
            }

            $relativePaths[] = ltrim($absolutePath, '/');
        }

        $command = array_merge(['tar', '-czf', $archivePath, '-C', '/'], $relativePaths);
        $process = new Process($command, base_path(), null, null, 300);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException("Could not archive {$label}: {$process->getErrorOutput()}");
        }

        return $archivePath;
    }

    /**
     * @param  list<string>  $artifactPaths
     * @param  list<string>  $extraPaths
     */
    private function createManifest(
        string $tempDirectory,
        string $timestamp,
        array $artifactPaths,
        int $retention,
        string $remotePrefix,
        array $extraPaths
    ): string {
        $manifestPath = "{$tempDirectory}/manifest-{$timestamp}.json";

        $manifest = [
            'created_at_utc' => now()->utc()->toIso8601String(),
            'hostname' => gethostname() ?: null,
            'app_env' => config('app.env'),
            'app_url' => config('app.url'),
            'git_commit' => $this->resolveGitCommit(),
            'database_connection' => config('database.default'),
            'database_driver' => config('database.connections.'.config('database.default').'.driver'),
            'database_backup_target' => $this->resolveDatabaseBackupTarget(),
            'remote_prefix' => $remotePrefix,
            'retention_days' => $retention,
            'included_paths' => [
                base_path('.env'),
                storage_path(),
            ],
            'extra_paths' => $extraPaths,
            'artifacts' => array_map(fn (string $path): array => [
                'filename' => basename($path),
                'bytes' => filesize($path) ?: 0,
                'sha256' => hash_file('sha256', $path),
            ], $artifactPaths),
        ];

        File::put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        return $manifestPath;
    }

    private function resolveGitCommit(): ?string
    {
        $process = new Process(['git', 'rev-parse', 'HEAD'], base_path(), null, null, 10);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $commit = trim($process->getOutput());

        return $commit !== '' ? $commit : null;
    }

    private function resolveDatabaseBackupTarget(): string
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");
        $driver = (string) ($config['driver'] ?? 'unknown');

        return match ($driver) {
            'sqlite' => (string) ($config['database'] ?? ''),
            'mysql', 'mariadb' => sprintf(
                '%s://%s/%s',
                $driver,
                ($config['host'] ?? $config['unix_socket'] ?? 'localhost'),
                ($config['database'] ?? '')
            ),
            default => $driver,
        };
    }

    private function pruneOldRestorePoints(string $diskName, string $remotePrefix, int $retention): void
    {
        $disk = Storage::disk($diskName);
        $grouped = [];

        foreach ($disk->allFiles($remotePrefix) as $path) {
            $relative = Str::after($path, "{$remotePrefix}/");

            if ($relative === $path || ! str_contains($relative, '/')) {
                continue;
            }

            $restorePoint = Str::before($relative, '/');
            $grouped[$restorePoint][] = $path;
        }

        if (count($grouped) <= $retention) {
            return;
        }

        krsort($grouped);
        $staleRestorePoints = array_slice(array_keys($grouped), $retention);

        foreach ($staleRestorePoints as $restorePoint) {
            $disk->deleteDirectory("{$remotePrefix}/{$restorePoint}");
            $this->components->warn("Pruned remote restore point {$remotePrefix}/{$restorePoint}");
        }
    }
}
