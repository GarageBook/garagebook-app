<x-filament-panels::page>
    <div class="-mx-4 space-y-8 overflow-x-hidden rounded-[2rem] bg-slate-100/80 px-4 py-2 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
        <section class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-950/5">
            <div class="flex flex-col gap-6 px-6 py-7 sm:px-8 lg:flex-row lg:items-center lg:justify-between">
                <div class="max-w-3xl space-y-4">
                    <span class="inline-flex items-center gap-2 rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700 ring-1 ring-sky-200">
                        <span class="h-2 w-2 rounded-full bg-sky-500"></span>
                        Premium growth reporting
                    </span>

                    <div class="space-y-2">
                        <h1 class="text-3xl font-semibold tracking-tight text-slate-950">
                            Growth dashboard
                        </h1>
                        <p class="max-w-2xl text-sm leading-6 text-slate-600">
                            Experimenteel beheer-dashboard met acquisitie-, SEO-, funnel- en activatiestatistieken op basis van lokaal opgeslagen data.
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3 lg:justify-end">
                    <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                        Lokaal opgeslagen data
                    </span>
                    <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 shadow-sm">
                        <span class="h-2 w-2 rounded-full bg-sky-400"></span>
                        Laatste 30 dagen
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

            @livewire(\App\Filament\Widgets\GrowthProspectFollowUpWidget::class)

            @livewire(\App\Filament\Widgets\GrowthSeoIntelligenceWidget::class)

            <div class="grid gap-8 xl:grid-cols-[1.05fr,0.95fr]">
                @livewire(\App\Filament\Widgets\GrowthLandingPageConversionWidget::class)
                @livewire(\App\Filament\Widgets\GrowthProductActivationFunnelWidget::class)
            </div>

            @livewire(\App\Filament\Widgets\GrowthRecentActivityWidget::class)
        </div>
    </div>
</x-filament-panels::page>
