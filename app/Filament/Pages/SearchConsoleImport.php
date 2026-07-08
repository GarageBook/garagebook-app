<?php

namespace App\Filament\Pages;

use App\Models\GscImportSession;
use App\Services\Gsc\GscCsvImportService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Throwable;

class SearchConsoleImport extends Page
{
    use WithFileUploads;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?int $navigationSort = 192;

    protected string $view = 'filament.pages.search-console-import';

    public string $date = '';

    /** @var list<TemporaryUploadedFile> */
    public array $csvFiles = [];

    public ?TemporaryUploadedFile $pagesCsv = null;

    public ?TemporaryUploadedFile $queriesCsv = null;

    public string $overwrite = 'no';

    public bool $replace = false;

    public ?array $result = null;

    public array $history = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin() ?? false, 403);

        $this->date = now()->toDateString();
        $this->loadHistory();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'SEO';
    }

    public static function getNavigationLabel(): string
    {
        return 'Search Console Import';
    }

    public function getHeading(): string
    {
        return 'Search Console Import';
    }

    public function getTitle(): string
    {
        return 'Search Console Import';
    }

    public function getSubheading(): ?string
    {
        return 'Upload Google Search Console CSV-exports zonder CLI-workflow.';
    }

    public function import(GscCsvImportService $importer): void
    {
        $this->validate([
            'date' => ['required', 'date'],
            'csvFiles' => ['nullable', 'array', 'max:25'],
            'csvFiles.*' => ['file', 'mimes:csv', 'max:20480'],
            'pagesCsv' => ['nullable', 'file', 'mimes:csv', 'max:20480'],
            'queriesCsv' => ['nullable', 'file', 'mimes:csv', 'max:20480'],
            'overwrite' => ['required', 'in:no,yes'],
            'replace' => ['boolean'],
        ]);

        if ($this->csvFiles === [] && ! $this->pagesCsv && ! $this->queriesCsv) {
            $this->addError('csvFiles', 'Upload minimaal een CSV-bestand.');

            return;
        }

        $date = Carbon::parse($this->date)->toDateString();
        $storedFiles = [];

        try {
            foreach ($this->csvFiles as $file) {
                $storedPath = $file->store('gsc-imports', 'local');
                $storedFiles[] = [
                    'path' => Storage::disk('local')->path($storedPath),
                    'name' => $file->getClientOriginalName(),
                    'delete_after' => true,
                ];
            }

            if ($this->pagesCsv) {
                $storedPath = $this->pagesCsv->store('gsc-imports', 'local');
                $storedFiles[] = [
                    'path' => Storage::disk('local')->path($storedPath),
                    'name' => $this->pagesCsv->getClientOriginalName(),
                    'delete_after' => true,
                ];
            }

            if ($this->queriesCsv) {
                $storedPath = $this->queriesCsv->store('gsc-imports', 'local');
                $storedFiles[] = [
                    'path' => Storage::disk('local')->path($storedPath),
                    'name' => $this->queriesCsv->getClientOriginalName(),
                    'delete_after' => true,
                ];
            }

            $summary = $importer->importBulkSession($storedFiles, $date, $this->replace || $this->overwrite === 'yes', auth()->id());

            Cache::flush();

            $this->result = $this->resultPayload($summary);
            $this->reset('csvFiles', 'pagesCsv', 'queriesCsv');
            $this->overwrite = 'no';
            $this->replace = false;
            $this->loadHistory();

            $notification = Notification::make()
                ->title('Search Console import afgerond')
                ->body(sprintf(
                    '%s pagina\'s, %s zoekwoorden, %s landen, %s apparaten en %s datumregels verwerkt in %s seconden.',
                    number_format($summary['pages_imported'], 0, ',', '.'),
                    number_format($summary['queries_imported'], 0, ',', '.'),
                    number_format($summary['countries_imported'], 0, ',', '.'),
                    number_format($summary['devices_imported'], 0, ',', '.'),
                    number_format($summary['date_rows_imported'], 0, ',', '.'),
                    number_format($summary['duration_ms'] / 1000, 1, ',', '.'),
                ));

            $notification = match ($summary['status']) {
                'completed' => $notification->success(),
                'completed_with_warnings' => $notification->warning(),
                default => $notification->danger(),
            };

            $notification->send();
        } catch (Throwable $exception) {
            $this->result = [
                'status' => 'failed',
                'errors' => [$exception->getMessage()],
                'warnings' => [],
            ];
            $this->loadHistory();

            Notification::make()
                ->title('Search Console import mislukt')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    private function loadHistory(): void
    {
        $this->history = GscImportSession::query()
            ->with('user:id,name,email')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (GscImportSession $session): array => [
                'date' => $session->import_date?->toDateString(),
                'status' => $session->status,
                'processed_files' => $session->processed_files,
                'skipped_files' => $session->skipped_files,
                'pages' => $session->pages_imported,
                'queries' => $session->queries_imported,
                'countries' => $session->countries_imported,
                'devices' => $session->devices_imported,
                'search_appearances' => $session->search_appearances_imported,
                'date_rows' => $session->date_rows_imported,
                'user' => $session->user?->name ?: $session->user?->email ?: '-',
                'duration' => number_format($session->duration_ms / 1000, 1, ',', '.').'s',
                'warnings' => $session->warnings ?? [],
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function resultPayload(array $summary): array
    {
        return [
            'status' => $summary['status'],
            'session_id' => $summary['session_id'] ?? null,
            'pages' => $summary['pages_imported'] ?? 0,
            'queries' => $summary['queries_imported'] ?? 0,
            'countries' => $summary['countries_imported'] ?? 0,
            'devices' => $summary['devices_imported'] ?? 0,
            'search_appearances' => $summary['search_appearances_imported'] ?? 0,
            'date_rows' => $summary['date_rows_imported'] ?? 0,
            'processed_files' => $summary['processed_files'] ?? 0,
            'skipped_files' => $summary['skipped_files'] ?? 0,
            'duration_seconds' => (($summary['duration_ms'] ?? 0) / 1000),
            'warnings' => $summary['warnings'] ?? [],
            'errors' => $summary['errors'] ?? [],
        ];
    }
}
