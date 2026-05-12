<?php

namespace Tests\Unit;

use App\Services\Trips\GpxTripParser;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GpxTripParserTest extends TestCase
{
    public function test_it_parses_a_gpx_fixture_into_trip_data(): void
    {
        $parser = app(GpxTripParser::class);
        $contents = file_get_contents(base_path('tests/Fixtures/trips/sample.gpx'));

        $parsed = $parser->parse($contents);

        $this->assertCount(4, $parsed->points);
        $this->assertSame('2026-05-12T08:00:00Z', $parsed->startedAt);
        $this->assertSame('2026-05-12T08:20:00Z', $parsed->endedAt);
        $this->assertSame(1200, $parsed->durationSeconds);
        $this->assertNotNull($parsed->bounds);
        $this->assertSame('Feature', $parsed->geojson['type']);
        $this->assertSame('LineString', $parsed->geojson['geometry']['type']);
        $this->assertCount(4, $parsed->geojson['geometry']['coordinates']);
    }

    public function test_it_rejects_gpx_files_that_exceed_the_safe_size_limit(): void
    {
        Storage::fake('local');

        $parser = app(GpxTripParser::class);
        $path = 'trip-uploads/too-large.gpx';

        Storage::disk('local')->put($path, str_repeat('a', GpxTripParser::MAX_SOURCE_FILE_BYTES + 1));

        $this->expectExceptionMessage('Het GPX-bestand is te groot om veilig te verwerken.');

        $parser->parseFromDisk('local', $path);
    }
}
