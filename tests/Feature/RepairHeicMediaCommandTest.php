<?php

namespace Tests\Feature;

use App\Models\MaintenanceLog;
use App\Models\TripLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Support\ImageUploadSupport;
use App\Support\RasterImageProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class RepairHeicMediaCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_without_changing_files_or_database(): void
    {
        Storage::fake('public');
        $vehicle = $this->vehicle(['photo' => 'vehicle-photos/dry-run.heic']);
        Storage::disk('public')->put($vehicle->photo, $this->contentDetectedHeicBytes());
        $this->bindSuccessfulFakeProcessor();

        $this->artisan('garagebook:repair-heic-media', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame('vehicle-photos/dry-run.heic', $vehicle->refresh()->photo);
        Storage::disk('public')->assertExists('vehicle-photos/dry-run.heic');
        Storage::disk('public')->assertMissing('vehicle-photos/dry-run.jpg');
    }

    public function test_repair_changes_database_path_to_jpg_and_removes_old_heic_after_success(): void
    {
        Storage::fake('public');
        $vehicle = $this->vehicle(['photo' => 'vehicle-photos/repair.heic']);
        Storage::disk('public')->put($vehicle->photo, $this->contentDetectedHeicBytes());
        $this->bindSuccessfulFakeProcessor();

        $this->artisan('garagebook:repair-heic-media')
            ->assertSuccessful();

        $this->assertSame('vehicle-photos/repair.jpg', $vehicle->refresh()->photo);
        Storage::disk('public')->assertExists('vehicle-photos/repair.jpg');
        Storage::disk('public')->assertMissing('vehicle-photos/repair.heic');
        $this->assertTrue(app(RasterImageProcessor::class)->isJpegFile(Storage::disk('public')->path('vehicle-photos/repair.jpg')));
    }

    public function test_real_heic_fixture_is_repaired_to_real_jpg_when_runtime_supports_heic(): void
    {
        $this->skipIfHeicRuntimeIsUnavailable();

        Storage::fake('public');
        $vehicle = $this->vehicle(['photo' => 'vehicle-photos/iphone.heic']);
        Storage::disk('public')->put($vehicle->photo, file_get_contents(base_path('tests/Fixtures/images/iphone-sample.heic')));

        $this->artisan('garagebook:repair-heic-media')
            ->assertSuccessful();

        $this->assertSame('vehicle-photos/iphone.jpg', $vehicle->refresh()->photo);
        Storage::disk('public')->assertExists('vehicle-photos/iphone.jpg');
        Storage::disk('public')->assertMissing('vehicle-photos/iphone.heic');
        $this->assertTrue(app(RasterImageProcessor::class)->isJpegFile(Storage::disk('public')->path('vehicle-photos/iphone.jpg')));
    }

    public function test_content_detected_heic_with_uppercase_jpg_extension_is_safely_repaired(): void
    {
        $this->skipIfHeicRuntimeIsUnavailable();

        Storage::fake('public');
        $vehicle = $this->vehicle();
        $sourcePath = 'maintenance-attachments/01M0TKD41MXGNMG5AV93XHSSDQ.JPG';
        $targetPath = 'maintenance-attachments/01M0TKD41MXGNMG5AV93XHSSDQ.jpg';
        $log = MaintenanceLog::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'HEIC met JPG-extensie',
            'maintenance_date' => now()->toDateString(),
            'km_reading' => 1000,
            'attachments' => [$sourcePath],
        ]);
        Storage::disk('public')->put($sourcePath, file_get_contents(base_path('tests/Fixtures/images/iphone-sample.heic')));

        $this->assertTrue(ImageUploadSupport::isHeifFile(Storage::disk('public')->path($sourcePath)));

        $this->artisan('garagebook:repair-heic-media')->assertSuccessful();

        $this->assertSame([$targetPath], $log->refresh()->attachments);
        Storage::disk('public')->assertExists($targetPath);
        Storage::disk('public')->assertMissing($sourcePath);
        $this->assertTrue(app(RasterImageProcessor::class)->isJpegFile(Storage::disk('public')->path($targetPath)));
        $this->assertSame('image/jpeg', Storage::disk('public')->mimeType($targetPath));
        $this->assertSame([], array_values(array_filter(
            Storage::disk('public')->allFiles('maintenance-attachments'),
            fn (string $path): bool => str_contains($path, '.tmp.jpg')
        )));
    }

    public function test_uppercase_jpg_heic_failure_preserves_original_file_and_database_state(): void
    {
        Storage::fake('public');
        $vehicle = $this->vehicle(['photo' => 'vehicle-photos/fail.JPG']);
        $original = $this->contentDetectedHeicBytes();
        Storage::disk('public')->put($vehicle->photo, $original);
        $this->bindFailingFakeProcessor();

        $this->artisan('garagebook:repair-heic-media')->assertFailed();

        $this->assertSame('vehicle-photos/fail.JPG', $vehicle->refresh()->photo);
        Storage::disk('public')->assertExists('vehicle-photos/fail.JPG');
        Storage::disk('public')->assertMissing('vehicle-photos/fail.jpg');
        $this->assertSame($original, Storage::disk('public')->get('vehicle-photos/fail.JPG'));
    }

    public function test_repair_stops_without_changes_when_heic_runtime_is_missing(): void
    {
        Storage::fake('public');
        $vehicle = $this->vehicle(['photo' => 'vehicle-photos/runtime.heic']);
        Storage::disk('public')->put($vehicle->photo, $this->contentDetectedHeicBytes());
        $this->bindRuntimeMissingFakeProcessor();

        $this->artisan('garagebook:repair-heic-media')
            ->assertFailed();

        $this->assertSame('vehicle-photos/runtime.heic', $vehicle->refresh()->photo);
        Storage::disk('public')->assertExists('vehicle-photos/runtime.heic');
        Storage::disk('public')->assertMissing('vehicle-photos/runtime.jpg');
    }

    public function test_conversion_failure_keeps_original_file_and_database_path(): void
    {
        Storage::fake('public');
        $vehicle = $this->vehicle(['photo' => 'vehicle-photos/fail.heic']);
        Storage::disk('public')->put($vehicle->photo, $this->contentDetectedHeicBytes());
        $this->bindFailingFakeProcessor();

        $this->artisan('garagebook:repair-heic-media')
            ->assertFailed();

        $this->assertSame('vehicle-photos/fail.heic', $vehicle->refresh()->photo);
        Storage::disk('public')->assertExists('vehicle-photos/fail.heic');
        Storage::disk('public')->assertMissing('vehicle-photos/fail.jpg');
    }

    public function test_missing_file_does_not_change_database(): void
    {
        Storage::fake('public');
        $vehicle = $this->vehicle(['photo' => 'vehicle-photos/missing.heic']);
        $this->bindSuccessfulFakeProcessor();

        $this->artisan('garagebook:repair-heic-media')
            ->assertSuccessful();

        $this->assertSame('vehicle-photos/missing.heic', $vehicle->refresh()->photo);
        Storage::disk('public')->assertMissing('vehicle-photos/missing.jpg');
    }

    public function test_fake_heic_with_png_content_is_not_converted(): void
    {
        Storage::fake('public');
        $vehicle = $this->vehicle(['photo' => 'vehicle-photos/fake.heic']);
        $this->putPngImage('vehicle-photos/fake.heic');
        $this->bindSuccessfulFakeProcessor();

        $this->artisan('garagebook:repair-heic-media')
            ->assertSuccessful();

        $this->assertSame('vehicle-photos/fake.heic', $vehicle->refresh()->photo);
        Storage::disk('public')->assertExists('vehicle-photos/fake.heic');
        Storage::disk('public')->assertMissing('vehicle-photos/fake.jpg');
    }

    public function test_command_is_idempotent_after_successful_repair(): void
    {
        Storage::fake('public');
        $vehicle = $this->vehicle(['photos' => ['vehicle-photos/idempotent.heic']]);
        Storage::disk('public')->put('vehicle-photos/idempotent.heic', $this->contentDetectedHeicBytes());
        $this->bindSuccessfulFakeProcessor();

        $this->artisan('garagebook:repair-heic-media')->assertSuccessful();
        $this->artisan('garagebook:repair-heic-media')->assertSuccessful();

        $this->assertSame(['vehicle-photos/idempotent.jpg'], $vehicle->refresh()->photos);
        Storage::disk('public')->assertExists('vehicle-photos/idempotent.jpg');
        Storage::disk('public')->assertMissing('vehicle-photos/idempotent.heic');
    }

    public function test_jpg_png_and_webp_are_not_touched_by_repair_command(): void
    {
        Storage::fake('public');
        $vehicle = $this->vehicle([
            'photo' => 'vehicle-photos/photo.jpg',
            'photos' => ['vehicle-photos/photo.png'],
            'media_attachments' => ['vehicle-attachments/photo.webp'],
        ]);
        $this->putJpegImage('vehicle-photos/photo.jpg');
        $this->putPngImage('vehicle-photos/photo.png');
        $this->putWebpImage('vehicle-attachments/photo.webp');
        $this->bindSuccessfulFakeProcessor();

        $this->artisan('garagebook:repair-heic-media')->assertSuccessful();

        $vehicle->refresh();
        $this->assertSame('vehicle-photos/photo.jpg', $vehicle->photo);
        $this->assertSame(['vehicle-photos/photo.png'], $vehicle->photos);
        $this->assertSame(['vehicle-attachments/photo.webp'], $vehicle->media_attachments);
    }

    public function test_repair_updates_vehicle_document_metadata(): void
    {
        Storage::fake('local');
        $vehicle = $this->vehicle();
        $document = VehicleDocument::create([
            'vehicle_id' => $vehicle->id,
            'title' => 'Factuur',
            'document_type' => 'invoice',
            'file_path' => 'vehicle-documents/invoice.heic',
            'mime_type' => 'image/heic',
            'file_size' => 100,
        ]);
        Storage::disk('local')->put($document->file_path, $this->contentDetectedHeicBytes());
        $this->bindSuccessfulFakeProcessor();

        $this->artisan('garagebook:repair-heic-media')->assertSuccessful();

        $document->refresh();
        $this->assertSame('vehicle-documents/invoice.jpg', $document->file_path);
        $this->assertSame('image/jpeg', $document->mime_type);
        $this->assertGreaterThan(0, $document->file_size);
    }

    public function test_trip_photo_paths_are_repaired_on_local_disk(): void
    {
        Storage::fake('local');
        $vehicle = $this->vehicle();
        $trip = TripLog::create([
            'user_id' => $vehicle->user_id,
            'vehicle_id' => $vehicle->id,
            'source_file_path' => 'trip-uploads/sample.gpx',
            'photos' => ['trip-photos/photo.heic'],
        ]);
        Storage::disk('local')->put('trip-photos/photo.heic', $this->contentDetectedHeicBytes());
        $this->bindSuccessfulFakeProcessor();

        $this->artisan('garagebook:repair-heic-media')->assertSuccessful();

        $this->assertSame(['trip-photos/photo.jpg'], $trip->refresh()->photos);
        Storage::disk('local')->assertExists('trip-photos/photo.jpg');
        Storage::disk('local')->assertMissing('trip-photos/photo.heic');
    }

    private function skipIfHeicRuntimeIsUnavailable(): void
    {
        try {
            app(RasterImageProcessor::class)->ensureHeifRuntimeIsAvailable();
        } catch (RuntimeException $exception) {
            $this->markTestSkipped($exception->getMessage());
        }
    }

    private function bindSuccessfulFakeProcessor(): void
    {
        $this->app->instance(RasterImageProcessor::class, new class extends RasterImageProcessor
        {
            public function ensureHeifRuntimeIsAvailable(): void {}

            public function convertHeifToJpeg(string $diskName, string $path, int $maxDimension = 2200, int $quality = 82, bool $deleteOriginal = true): string
            {
                $target = str_replace(['.heic', '.HEIC', '.heif', '.HEIF'], '.jpg', $path);
                $image = imagecreatetruecolor(32, 24);
                imagefill($image, 0, 0, imagecolorallocate($image, 30, 90, 160));

                ob_start();
                imagejpeg($image, null, 82);
                $contents = ob_get_clean();
                imagedestroy($image);

                Storage::disk($diskName)->put($target, $contents);

                if ($deleteOriginal && $target !== $path && Storage::disk($diskName)->exists($path)) {
                    Storage::disk($diskName)->delete($path);
                }

                return $target;
            }
        });
    }

    private function bindRuntimeMissingFakeProcessor(): void
    {
        $this->app->instance(RasterImageProcessor::class, new class extends RasterImageProcessor
        {
            public function ensureHeifRuntimeIsAvailable(): void
            {
                throw new RuntimeException('runtime missing');
            }

            public function convertHeifToJpeg(string $diskName, string $path, int $maxDimension = 2200, int $quality = 82, bool $deleteOriginal = true): string
            {
                throw new RuntimeException('conversion should not run');
            }
        });
    }

    private function bindFailingFakeProcessor(): void
    {
        $this->app->instance(RasterImageProcessor::class, new class extends RasterImageProcessor
        {
            public function ensureHeifRuntimeIsAvailable(): void {}

            public function convertHeifToJpeg(string $diskName, string $path, int $maxDimension = 2200, int $quality = 82, bool $deleteOriginal = true): string
            {
                throw new RuntimeException('conversion failed');
            }
        });
    }

    private function vehicle(array $attributes = []): Vehicle
    {
        $user = User::factory()->create();

        return Vehicle::create(array_merge([
            'user_id' => $user->id,
            'brand' => 'BMW',
            'model' => 'R 1250 GS',
            'current_km' => 1000,
        ], $attributes));
    }

    private function contentDetectedHeicBytes(): string
    {
        return pack('N', 28).'ftypheic'.pack('N', 0).'heicmif1'.'not-real-image-data';
    }

    private function putPngImage(string $path): void
    {
        $image = imagecreatetruecolor(24, 24);
        imagefill($image, 0, 0, imagecolorallocate($image, 40, 120, 200));
        $this->writeImage('public', $path, fn (string $target) => imagepng($image, $target));
        imagedestroy($image);
    }

    private function putJpegImage(string $path): void
    {
        $image = imagecreatetruecolor(24, 24);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 120, 40));
        $this->writeImage('public', $path, fn (string $target) => imagejpeg($image, $target, 82));
        imagedestroy($image);
    }

    private function putWebpImage(string $path): void
    {
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('GD WebP support is not available.');
        }

        $image = imagecreatetruecolor(24, 24);
        imagefill($image, 0, 0, imagecolorallocate($image, 80, 200, 120));
        $this->writeImage('public', $path, fn (string $target) => imagewebp($image, $target, 82));
        imagedestroy($image);
    }

    private function writeImage(string $disk, string $path, callable $writer): void
    {
        $fullPath = Storage::disk($disk)->path($path);

        if (! is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0777, true);
        }

        $writer($fullPath);
    }
}
