<x-filament-panels::page>
    <div class="space-y-6">
        @if (\App\Filament\Widgets\GrowthSummaryStats::canView())
            @livewire(\App\Filament\Widgets\GrowthSummaryStats::class)
        @endif

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            @if (\App\Filament\Widgets\TopSearchQueriesWidget::canView())
                @livewire(\App\Filament\Widgets\TopSearchQueriesWidget::class)
            @endif

            @if (\App\Filament\Widgets\TopSeoPagesWidget::canView())
                @livewire(\App\Filament\Widgets\TopSeoPagesWidget::class)
            @endif
        </div>

        @if (\App\Filament\Widgets\TopVisitedPagesWidget::canView())
            @livewire(\App\Filament\Widgets\TopVisitedPagesWidget::class)
        @endif
    </div>
</x-filament-panels::page>
