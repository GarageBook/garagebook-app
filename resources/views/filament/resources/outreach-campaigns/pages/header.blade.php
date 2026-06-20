<div style="display:flex; flex-direction:column; gap:16px;">
    <x-filament-panels::header
        :actions="$actions"
        :heading="$heading"
        :subheading="$subheading"
    >
        @if ($heading instanceof \Illuminate\Contracts\Support\Htmlable)
            <x-slot name="heading">
                {{ $heading }}
            </x-slot>
        @endif

        @if ($subheading instanceof \Illuminate\Contracts\Support\Htmlable)
            <x-slot name="subheading">
                {{ $subheading }}
            </x-slot>
        @endif
    </x-filament-panels::header>

    @include('filament.outreach.quota-banner', ['banner' => $quotaBanner])
</div>
