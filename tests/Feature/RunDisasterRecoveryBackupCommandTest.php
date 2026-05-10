<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RunDisasterRecoveryBackupCommandTest extends TestCase
{
    private string $databasePath;
    private string $fakeMysqlDumpPath;
    private string $originalStoragePath;
    private string $isolatedStoragePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalStoragePath = $this->app->storagePath();
        $this->isolatedStoragePath = rtrim(sys_get_temp_dir(), '/').'/garagebook-test-storage-'.uniqid('', true);
        File::deleteDirectory($this->isolatedStoragePath);
        File::ensureDirectoryExists($this->isolatedStoragePath.'/app/public');
        $this->app->useStoragePath($this->isolatedStoragePath);

        $this->databasePath = database_path('testing-backup.sqlite');
        File::delete($this->databasePath);
        File::put($this->databasePath, '');

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', $this->databasePath);
        DB::purge('sqlite');

        Storage::fake('backups');

        Config::set('backups.remote_prefix', 'daily');
        Config::set('backups.retention_days', 7);
        Config::set('backups.extra_paths', []);
        Config::set('filesystems.disks.backups.bucket', 'test-bucket');
        Config::set('filesystems.disks.backups.endpoint', 'https://example-b2.test');
        $this->fakeMysqlDumpPath = rtrim(sys_get_temp_dir(), '/').'/fake-mysqldump.sh';

        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
    }

    protected function tearDown(): void
    {
        foreach (glob(rtrim(sys_get_temp_dir(), '/').'/garagebook-backup-*') ?: [] as $temporaryDirectory) {
            File::deleteDirectory($temporaryDirectory);
        }

        $this->app->useStoragePath($this->originalStoragePath);
        File::deleteDirectory($this->isolatedStoragePath);
        File::delete($this->fakeMysqlDumpPath);
        DB::disconnect('sqlite');
        File::delete($this->databasePath);

        parent::tearDown();
    }

    public function test_command_uploads_expected_artifacts_to_backups_disk(): void
    {
        File::ensureDirectoryExists(storage_path('app/public'));
        File::put(storage_path('app/public/test-backup-file.txt'), 'backup me');

        $this->artisan('backup:run-disaster-recovery')
            ->assertSuccessful();

        $files = Storage::disk('backups')->allFiles('daily');

        $this->assertCount(4, $files);
        $this->assertNotEmpty($this->findUploadedFile($files, 'database-'));
        $this->assertNotEmpty($this->findUploadedFile($files, 'env-'));
        $this->assertNotEmpty($this->findUploadedFile($files, 'storage-'));

        $manifestPath = $this->findUploadedFile($files, 'manifest-');
        $this->assertNotEmpty($manifestPath);

        $manifest = json_decode(Storage::disk('backups')->get($manifestPath), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('sqlite', $manifest['database_driver']);
        $this->assertSame([], $manifest['extra_paths']);
        $this->assertSame('daily', $manifest['remote_prefix']);
        $this->assertCount(3, $manifest['artifacts']);
    }

    public function test_command_can_create_mysql_backup_with_configured_dump_binary(): void
    {
        File::put($this->fakeMysqlDumpPath, <<<'BASH'
#!/bin/bash
set -euo pipefail
for arg in "$@"; do
  case "$arg" in
    --result-file=*)
      target="${arg#--result-file=}"
      printf '%s\n' 'fake mysql dump' > "$target"
      exit 0
      ;;
  esac
done
exit 1
BASH);
        chmod($this->fakeMysqlDumpPath, 0755);

        Config::set('database.default', 'mysql');
        Config::set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => 'garagebook',
            'username' => 'root',
            'password' => 'secret',
            'unix_socket' => '',
        ]);
        Config::set('backups.mysql_dump_binary', $this->fakeMysqlDumpPath);

        $this->artisan('backup:run-disaster-recovery')
            ->assertSuccessful();

        $files = Storage::disk('backups')->allFiles('daily');

        $sqlPath = $this->findUploadedFile($files, 'database-');
        $this->assertStringEndsWith('.sql', $sqlPath);
        $this->assertSame('fake mysql dump'.PHP_EOL, Storage::disk('backups')->get($sqlPath));
    }

    public function test_command_prunes_older_remote_restore_points(): void
    {
        Storage::disk('backups')->put('daily/2026-05-01T000000Z/manifest-old.json', '{}');
        Storage::disk('backups')->put('daily/2026-05-02T000000Z/manifest-older.json', '{}');

        $this->artisan('backup:run-disaster-recovery', ['--keep' => 1])
            ->assertSuccessful();

        $restorePoints = [];

        foreach (Storage::disk('backups')->allFiles('daily') as $path) {
            $parts = explode('/', $path);
            $restorePoints[$parts[1]] = true;
        }

        $this->assertCount(1, $restorePoints);
    }

    /**
     * @param  list<string>  $files
     */
    private function findUploadedFile(array $files, string $prefix): string
    {
        foreach ($files as $file) {
            if (str_starts_with(basename($file), $prefix)) {
                return $file;
            }
        }

        return '';
    }
}
