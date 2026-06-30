<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Community2026DiscoveryCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_input_writes_discovery_csv(): void
    {
        $input = $this->writeTempFile('discovery-input.csv', implode(PHP_EOL, [
            'name,website,email,phone,city,province,source_url,source_type,prospect_type,prospect_subtype,notes',
            'Motorclub Noord,https://motorclub-noord.example,info@motorclub-noord.example,0612345678,Groningen,Groningen,https://source.example/noord,csv,community,motorcycle_club,Club met leden',
        ]));

        $output = base_path('storage/app/imports/community2026_discovered.csv');
        File::delete($output);

        $this->artisan('garagebook:discover-community2026', [
            '--file' => $input,
        ])->assertSuccessful();

        $this->assertFileExists($output);
        $rows = $this->readCsv($output);

        $this->assertSame(['name', 'website', 'email', 'phone', 'city', 'province', 'source_url', 'source_type', 'prospect_type', 'prospect_subtype', 'notes', 'quality_score', 'quality_flags', 'quality_verdict', 'quality_reason'], $rows[0]);
        $this->assertSame('Motorclub Noord', $rows[1][0]);
        $this->assertSame('https://motorclub-noord.example', $rows[1][1]);
        $this->assertSame('info@motorclub-noord.example', $rows[1][2]);
        $this->assertSame('0612345678', $rows[1][3]);
        $this->assertSame('community', $rows[1][8]);
        $this->assertSame('motorcycle_club', $rows[1][9]);
    }

    public function test_json_input_writes_discovery_csv(): void
    {
        $input = $this->writeTempFile('discovery-input.json', json_encode([
            [
                'name' => 'Oldtimer Vereniging',
                'website' => 'https://oldtimer-vereniging.example',
                'email' => 'info@oldtimer-vereniging.example',
                'phone' => '+31 20 123 4567',
                'city' => 'Utrecht',
                'province' => 'Utrecht',
                'source_url' => 'https://source.example/oldtimer',
                'source_type' => 'json',
                'prospect_type' => 'community',
                'prospect_subtype' => 'oldtimer_club',
                'notes' => 'JSON bron',
            ],
        ], JSON_THROW_ON_ERROR));

        $output = base_path('storage/app/imports/community2026_discovered.csv');
        File::delete($output);

        $this->artisan('garagebook:discover-community2026', [
            '--file' => $input,
        ])->assertSuccessful();

        $rows = $this->readCsv($output);

        $this->assertSame('Oldtimer Vereniging', $rows[1][0]);
        $this->assertSame('https://oldtimer-vereniging.example', $rows[1][1]);
        $this->assertSame('info@oldtimer-vereniging.example', $rows[1][2]);
        $this->assertSame('Utrecht', $rows[1][4]);
        $this->assertSame('Utrecht', $rows[1][5]);
        $this->assertSame('json', $rows[1][7]);
    }

    public function test_urls_option_can_read_from_text_file(): void
    {
        $seed = $this->writeTempFile('community2026_seed_urls.txt', implode(PHP_EOL, [
            'https://club.example',
        ]));

        Http::fake([
            '*contact*' => Http::response($this->contactPageHtml(), 200),
            '*' => Http::response($this->mainPageHtml(), 200),
        ]);

        $output = base_path('storage/app/imports/community2026_discovered.csv');
        File::delete($output);

        $this->artisan('garagebook:discover-community2026', [
            '--urls' => $seed,
        ])->assertSuccessful();

        Http::assertSentCount(2);
        $rows = $this->readCsv($output);

        $this->assertSame('Club Voorbeeld', $rows[1][0]);
    }

    public function test_website_input_extracts_contact_details(): void
    {
        Http::fake([
            '*contact*' => Http::response($this->contactPageHtml(), 200),
            '*' => Http::response($this->mainPageHtml(), 200),
        ]);

        $output = base_path('storage/app/imports/community2026_discovered.csv');
        File::delete($output);

        $this->artisan('garagebook:discover-community2026', [
            '--urls' => 'https://club.example',
        ])->assertSuccessful();

        Http::assertSentCount(2);

        $rows = $this->readCsv($output);

        $this->assertSame('Club Voorbeeld', $rows[1][0]);
        $this->assertSame('https://club.example', $rows[1][1]);
        $this->assertSame('info@club.example', $rows[1][2]);
        $this->assertSame('0612345678', $rows[1][3]);
        $this->assertSame('Amsterdam', $rows[1][4]);
        $this->assertSame('Noord-Holland', $rows[1][5]);
        $this->assertSame('https://club.example/contact', $rows[1][6]);
        $this->assertSame('website', $rows[1][7]);
        $this->assertSame('oldtimer_club', $rows[1][9]);
        $this->assertStringContainsString('social:', $rows[1][10]);
    }

    public function test_quality_filter_separates_rejected_rows(): void
    {
        $input = $this->writeTempFile('discovery-quality.csv', implode(PHP_EOL, [
            'name,website,email,phone,city,province,source_url,source_type,prospect_type,prospect_subtype,notes',
            'Motorclub Noord,https://motorclub-noord.example,info@motorclub-noord.example,0612345678,Groningen,Groningen,https://source.example/noord,csv,community,motorcycle_club,Club met leden',
            'Club Zonder Mail,https://club-zonder-mail.example,,,,,https://source.example/missing,csv,community,brand_club,Moet handmatig',
            '1-Cilinder- en Small-block dag,https://junk.example,,,,,https://source.example/junk,csv,community,oldtimer_club,Evenement',
            'Home,,,,,,https://source.example/home,csv,community,association,Home pagina',
        ]));

        $output = base_path('storage/app/imports/community2026_discovered.csv');
        $rejected = base_path('storage/app/imports/community2026_rejected.csv');
        File::delete($output);
        File::delete($rejected);

        $this->artisan('garagebook:discover-community2026', [
            '--file' => $input,
        ])->assertSuccessful()
            ->expectsOutput('discovered total: 4')
            ->expectsOutput('accepted: 1')
            ->expectsOutput('manual review: 1')
            ->expectsOutput('rejected: 2');

        $this->assertFileExists($output);
        $this->assertFileExists($rejected);

        $rows = $this->readCsv($output);
        $rejectedRows = $this->readCsv($rejected);

        $this->assertSame(['name', 'website', 'email', 'phone', 'city', 'province', 'source_url', 'source_type', 'prospect_type', 'prospect_subtype', 'notes', 'quality_score', 'quality_flags', 'quality_verdict', 'quality_reason'], $rows[0]);
        $this->assertSame('Motorclub Noord', $rows[1][0]);
        $this->assertSame('accepted', $rows[1][13]);
        $this->assertSame('Club Zonder Mail', $rows[2][0]);
        $this->assertSame('manual_review', $rows[2][13]);

        $this->assertSame('1-Cilinder- en Small-block dag', $rejectedRows[1][0]);
        $this->assertSame('rejected', $rejectedRows[1][13]);
        $this->assertSame('Home', $rejectedRows[2][0]);
        $this->assertSame('rejected', $rejectedRows[2][13]);
    }

    private function mainPageHtml(): string
    {
        return <<<'HTML'
<!doctype html>
<html>
<head>
    <title>Club Voorbeeld | Oldtimer vereniging</title>
    <meta property="og:site_name" content="Club Voorbeeld">
</head>
<body>
    <h1>Club Voorbeeld</h1>
    <p>Een oldtimer vereniging voor liefhebbers.</p>
    <a href="/contact">Contact</a>
    <a href="https://instagram.com/clubvoorbeeld">Instagram</a>
</body>
</html>
HTML;
    }

    private function contactPageHtml(): string
    {
        return <<<'HTML'
<!doctype html>
<html>
<head>
    <title>Contact</title>
    <meta itemprop="addressLocality" content="Amsterdam">
    <meta itemprop="addressRegion" content="Noord-Holland">
</head>
<body>
    <p>E-mail: <a href="mailto:info@club.example">info@club.example</a></p>
    <p>Telefoon: <a href="tel:+31612345678">06 1234 5678</a></p>
</body>
</html>
HTML;
    }

    private function writeTempFile(string $name, string $contents): string
    {
        $path = storage_path('framework/testing/'.$name);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);

        return $path;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->fail('Kan CSV niet lezen: '.$path);
        }

        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }
}
