<x-filament-panels::page>
    <div class="space-y-8">
        <section class="overflow-hidden rounded-3xl border border-slate-200/80 bg-gradient-to-br from-white via-slate-50 to-sky-50 shadow-sm ring-1 ring-slate-950/5 dark:border-white/10 dark:from-gray-900 dark:via-gray-900 dark:to-slate-900">
            <div class="flex flex-col gap-6 px-6 py-7 sm:px-8 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-3xl space-y-3">
                    <span class="inline-flex items-center gap-2 rounded-full bg-sky-100 px-3 py-1 text-xs font-medium text-sky-700 dark:bg-sky-500/15 dark:text-sky-200">
                        <span class="h-2 w-2 rounded-full bg-sky-500"></span>
                        Experimenteel beheer-dashboard
                    </span>
                    <div class="space-y-2">
                        <h1 class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">
                            Growth dashboard
                        </h1>
                        <p class="text-sm leading-6 text-slate-600 dark:text-slate-300">
                            Experimenteel beheer-dashboard met acquisitie-, SEO-, funnel- en activatiestatistieken op basis van lokaal opgeslagen data.
                        </p>
                    </div>
                </div>

                <div class="flex items-start lg:justify-end">
                    <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white/80 px-3 py-1.5 text-xs font-medium text-slate-600 shadow-sm backdrop-blur dark:border-white/10 dark:bg-white/5 dark:text-slate-300">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                        Lokaal opgeslagen data
                    </span>
                </div>
            </div>
        </section>

        <div class="space-y-8">
            @livewire(\App\Filament\Widgets\GrowthKpiOverviewWidget::class)

            <div class="grid gap-8 xl:grid-cols-2">
                @livewire(\App\Filament\Widgets\GrowthAcquisitionPerformanceWidget::class)
                @livewire(\App\Filament\Widgets\GrowthPartnerPerformanceWidget::class)
            </div>

            @livewire(\App\Filament\Widgets\GrowthSeoIntelligenceWidget::class)

            <div class="grid gap-8 xl:grid-cols-[1.05fr,0.95fr]">
                @livewire(\App\Filament\Widgets\GrowthLandingPageConversionWidget::class)
                @livewire(\App\Filament\Widgets\GrowthProductActivationFunnelWidget::class)
            </div>

            @livewire(\App\Filament\Widgets\GrowthRecentActivityWidget::class)
        </div>
    </div>
</x-filament-panels::page>
