<?php

namespace App\Filament\Resources\GrowthProspects\Pages;

use App\Filament\Resources\GrowthProspects\GrowthProspectResource;
use App\Services\Growth\GrowthProspectCsvImportService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Features\SupportFileUploads\WithFileUploads;

class ImportGrowthProspects extends Page
{
    use WithFileUploads;

    protected static string $resource = GrowthProspectResource::class;

    protected string $view = 'filament.resources.growth-prospects.pages.import-growth-prospects';

    public ?TemporaryUploadedFile $csvFile = null;

    public array $headers = [];

    public array $mapping = [];

    public array $previewRows = [];

    public array $summary = [
        'new' => 0,
        'update' => 0,
        'skipped' => 0,
    ];

    public ?array $importResult = null;

    public ?string $storedPath = null;

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function getTitle(): string
    {
        return 'Import prospects';
    }

    public function uploadCsv(GrowthProspectCsvImportService $importer): void
    {
        $this->validate([
            'csvFile' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $this->storedPath = $this->csvFile?->store('growth-prospect-imports', 'local');
        $this->importResult = null;

        $this->refreshPreview($importer);
    }

    public function refreshPreview(?GrowthProspectCsvImportService $importer = null): void
    {
        if (! $this->storedPath) {
            return;
        }

        $importer ??= app(GrowthProspectCsvImportService::class);
        $parsed = $importer->parsePath(Storage::disk('local')->path($this->storedPath));

        $this->headers = $parsed['headers'];

        if ($this->mapping === []) {
            $this->mapping = $importer->defaultMapping($this->headers);
        }

        $analysis = $importer->analyze($parsed, $this->mapping);
        $this->summary = $analysis['summary'];
        $this->previewRows = $analysis['preview'];
    }

    public function import(GrowthProspectCsvImportService $importer): void
    {
        if (! $this->storedPath) {
            Notification::make()
                ->title('Upload eerst een CSV-bestand')
                ->warning()
                ->send();

            return;
        }

        $parsed = $importer->parsePath(Storage::disk('local')->path($this->storedPath));
        $this->importResult = $importer->import($parsed, $this->mapping);
        $this->refreshPreview($importer);

        Notification::make()
            ->title('Prospects geimporteerd')
            ->body(sprintf(
                'Nieuw: %d. Bijgewerkt: %d. Overgeslagen: %d.',
                $this->importResult['created'],
                $this->importResult['updated'],
                $this->importResult['skipped'],
            ))
            ->success()
            ->send();
    }
}
