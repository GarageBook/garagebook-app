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

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
    }

    protected function tearDown(): void
    {
        foreach (glob(rtrim(sys_get_temp_dir(), '/').'/garagebook-backup-*') ?: [] as $temporaryDirectory) {
            File::deleteDirectory($temporaryDirectory);
        }

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
