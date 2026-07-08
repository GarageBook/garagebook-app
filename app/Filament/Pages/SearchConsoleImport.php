<?php

namespace App\Filament\Pages;

use App\Models\GscImportLog;
use App\Models\GscPageSnapshot;
use App\Models\GscQuerySnapshot;
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

    public ?TemporaryUploadedFile $pagesCsv = null;

    public ?TemporaryUploadedFile $queriesCsv = null;

    public string $overwrite = 'no';

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
            'pagesCsv' => ['required', 'file', 'mimes:csv', 'max:20480'],
            'queriesCsv' => ['required', 'file', 'mimes:csv', 'max:20480'],
            'overwrite' => ['required', 'in:no,yes'],
        ]);

        $date = Carbon::parse($this->date)->toDateString();
        $startedAt = microtime(true);
        $log = GscImportLog::query()->create([
            'date' => $date,
            'user_id' => auth()->id(),
            'status' => 'pending',
        ]);

        if ($this->hasSnapshotsForDate($date) && $this->overwrite !== 'yes') {
            $message = 'Er bestaat al GSC-data voor deze datum. Kies overschrijven om bestaande data te vervangen.';
            $this->finishLog($log, $startedAt, 'skipped', 0, 0, [$message], []);
            $this->result = $this->resultPayload($date, 0, 0, $startedAt, [$message], [], 'skipped');
            $this->loadHistory();

            Notification::make()
                ->title('Import overgeslagen')
                ->body($message)
                ->warning()
                ->send();

            return;
        }

        $storedPagesPath = $this->pagesCsv?->store('gsc-imports', 'local');
        $storedQueriesPath = $this->queriesCsv?->store('gsc-imports', 'local');

        try {
            $summary = $importer->import(
                $storedPagesPath ? Storage::disk('local')->path($storedPagesPath) : null,
                $storedQueriesPath ? Storage::disk('local')->path($storedQueriesPath) : null,
                $date,
                $this->overwrite === 'yes',
            );

            Cache::flush();

            if ($storedPagesPath) {
                Storage::disk('local')->delete($storedPagesPath);
            }

            if ($storedQueriesPath) {
                Storage::disk('local')->delete($storedQueriesPath);
            }

            $this->finishLog($log, $startedAt, 'success', $summary['pages'], $summary['queries'], [], []);
            $this->result = $this->resultPayload($date, $summary['pages'], $summary['queries'], $startedAt, [], [], 'success');
            $this->reset('pagesCsv', 'queriesCsv');
            $this->overwrite = 'no';
            $this->loadHistory();

            Notification::make()
                ->title('Search Console import voltooid')
                ->body(sprintf(
                    '%s pagina\'s geimporteerd. %s zoekwoorden geimporteerd. Import voltooid in %s seconden.',
                    number_format($summary['pages'], 0, ',', '.'),
                    number_format($summary['queries'], 0, ',', '.'),
                    number_format($this->result['duration_seconds'], 1, ',', '.'),
                ))
                ->success()
                ->send();
        } catch (Throwable $exception) {
            $this->finishLog($log, $startedAt, 'failed', 0, 0, [], [$exception->getMessage()]);
            $this->result = $this->resultPayload($date, 0, 0, $startedAt, [], [$exception->getMessage()], 'failed');
            $this->loadHistory();

            Notification::make()
                ->title('Search Console import mislukt')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    private function hasSnapshotsForDate(string $date): bool
    {
        return GscPageSnapshot::query()->whereDate('date', $date)->exists()
            || GscQuerySnapshot::query()->whereDate('date', $date)->exists();
    }

    private function loadHistory(): void
    {
        $this->history = GscImportLog::query()
            ->with('user:id,name,email')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (GscImportLog $log): array => [
                'date' => $log->date?->toDateString(),
                'pages' => $log->pages_imported,
                'queries' => $log->queries_imported,
                'user' => $log->user?->name ?: $log->user?->email ?: '-',
                'duration' => number_format($log->duration_ms / 1000, 1, ',', '.').'s',
                'status' => $log->status,
            ])
            ->all();
    }

    private function finishLog(
        GscImportLog $log,
        float $startedAt,
        string $status,
        int $pages,
        int $queries,
        array $warnings,
        array $errors,
    ): void {
        $log->update([
            'pages_imported' => $pages,
            'queries_imported' => $queries,
            'duration_ms' => $this->durationMs($startedAt),
            'status' => $status,
            'warnings' => $warnings,
            'errors' => $errors,
        ]);
    }

    private function resultPayload(
        string $date,
        int $pages,
        int $queries,
        float $startedAt,
        array $warnings,
        array $errors,
        string $status,
    ): array {
        $durationMs = $this->durationMs($startedAt);

        return [
            'date' => $date,
            'pages' => $pages,
            'queries' => $queries,
            'duration_ms' => $durationMs,
            'duration_seconds' => $durationMs / 1000,
            'warnings' => $warnings,
            'errors' => $errors,
            'status' => $status,
        ];
    }

    private function durationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
