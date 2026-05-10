<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\LocaleService;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

class LocalizationOverview extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static ?int $navigationSort = 220;

    protected string $view = 'filament.pages.localization-overview';

    #[Url(as: 'file')]
    public ?string $activeFile = null;

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
        return __('app.navigation.management');
    }

    public static function getNavigationLabel(): string
    {
        return __('app.locales.navigation_label');
    }

    public function mount(LocaleService $localeService): void
    {
        $availableFiles = $localeService->translationFiles();

        if ($this->activeFile && in_array($this->activeFile, $availableFiles, true)) {
            return;
        }

        $this->activeFile = $localeService->firstAvailableFile();
    }

    public function getHeading(): string
    {
        return __('app.locales.page_title');
    }

    public function getTitle(): string
    {
        return __('app.locales.page_title');
    }

    public function getSubheading(): ?string
    {
        return __('app.locales.page_subheading');
    }

    protected function getViewData(): array
    {
        $localeService = app(LocaleService::class);

        return [
            'activeFile' => $this->activeFile,
            'availableFiles' => $localeService->translationFiles(),
            'localeSummaries' => $localeService->localeSummaries(),
            'translationRows' => $this->activeFile
                ? $localeService->translationCatalog($this->activeFile)
                : [],
        ];
    }
}
