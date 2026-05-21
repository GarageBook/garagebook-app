<x-filament-panels::page>
    <div class="py-2">
        @if (\App\Filament\Widgets\GrowthSummaryStats::canView())
            <div style="margin-bottom: 40px;">
                @livewire(\App\Filament\Widgets\GrowthSummaryStats::class)
            </div>
        @endif

        <div class="grid grid-cols-1 gap-8 xl:grid-cols-2" style="margin-bottom: 40px;">
            @if (\App\Filament\Widgets\TopSearchQueriesWidget::canView())
                <div style="margin-bottom: 40px;">
                    @livewire(\App\Filament\Widgets\TopSearchQueriesWidget::class)
                </div>
            @endif

            @if (\App\Filament\Widgets\TopSeoPagesWidget::canView())
                <div style="margin-bottom: 40px;">
                    @livewire(\App\Filament\Widgets\TopSeoPagesWidget::class)
                </div>
            @endif
        </div>

        @if (\App\Filament\Widgets\TopVisitedPagesWidget::canView())
            <div style="margin-bottom: 40px;">
                @livewire(\App\Filament\Widgets\TopVisitedPagesWidget::class)
            </div>
        @endif
    </div>
</x-filament-panels::page>
