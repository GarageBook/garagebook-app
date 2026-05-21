<x-filament-panels::page>
    <div class="space-y-8 py-2">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-950">Experimenteel Growth dashboard</h2>
            <p class="mt-2 text-sm text-gray-600">
                Dit dashboard bestaat bewust naast het bestaande Analytics dashboard. Het gebruikt alleen lokaal opgeslagen data en maakt geen live Google API-calls tijdens render.
            </p>
        </div>

        @livewire(\App\Filament\Widgets\GrowthKpiOverviewWidget::class)
        @livewire(\App\Filament\Widgets\GrowthAcquisitionPerformanceWidget::class)
        @livewire(\App\Filament\Widgets\GrowthPartnerPerformanceWidget::class)
        @livewire(\App\Filament\Widgets\GrowthSeoIntelligenceWidget::class)
        @livewire(\App\Filament\Widgets\GrowthLandingPageConversionWidget::class)
        @livewire(\App\Filament\Widgets\GrowthProductActivationFunnelWidget::class)
        @livewire(\App\Filament\Widgets\GrowthRecentActivityWidget::class)
    </div>
</x-filament-panels::page>
