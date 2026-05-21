<x-filament-panels::page>
    <div class="space-y-10 py-2">
        @if (\App\Filament\Widgets\GrowthSummaryStats::canView())
            <div class="pb-2">
                @livewire(\App\Filament\Widgets\GrowthSummaryStats::class)
            </div>
        @endif

        <div class="grid grid-cols-1 gap-8 xl:grid-cols-2">
            @if (\App\Filament\Widgets\TopSearchQueriesWidget::canView())
                @livewire(\App\Filament\Widgets\TopSearchQueriesWidget::class)
            @endif

            @if (\App\Filament\Widgets\TopSeoPagesWidget::canView())
                @livewire(\App\Filament\Widgets\TopSeoPagesWidget::class)
            @endif
        </div>

        @if (\App\Filament\Widgets\TopVisitedPagesWidget::canView())
            <div class="pt-2">
                @livewire(\App\Filament\Widgets\TopVisitedPagesWidget::class)
            </div>
        @endif
    </div>
</x-filament-panels::page>
