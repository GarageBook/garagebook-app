<div class="{{ $card }}">
    <h3 class="mb-3 text-lg font-semibold">{{ $title }}</h3>
    @include('filament.pages.partials.search-console-table-inner', ['rows' => $rows, 'columns' => $columns])
</div>
