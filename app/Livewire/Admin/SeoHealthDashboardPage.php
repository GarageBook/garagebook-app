<?php

namespace App\Livewire\Admin;

use App\Services\Seo\SeoHealthService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class SeoHealthDashboardPage extends Page
{
    protected string $view = 'livewire.admin.seo-health-dashboard-page';

    /**
     * @var array<string, mixed>
     */
    public array $report = [];

    public function mount(SeoHealthService $seoHealthService): void
    {
        abort_unless(auth()->user()?->isAdmin() ?? false, 403);

        $this->report = $seoHealthService->report();
    }

    public function getTitle(): string|Htmlable
    {
        return 'SEO Health';
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'SEO Health';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return "Read-only controle van publieke garagepagina's, sitemap, canonical en structured data.";
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->url(route('admin.seo-health-dashboard.export'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray'),
        ];
    }
}
