@props([
    'heading' => null,
])

@php
    $heading ??= $this->getHeading();
@endphp

<div {{ $attributes->class(['fi-simple-page', 'gb-geratel-register-page']) }}>
    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_PAGE_START, scopes: $this->getRenderHookScopes()) }}

    <div class="fi-simple-page-content gb-geratel-register-page__content">
        <div class="gb-geratel-register-hero" data-geratel-register-hero>
            <img
                src="{{ asset('images/garagebook-geratel-verified.png') }}"
                alt="GarageBook Geratel Verified"
                class="gb-geratel-register-hero__logo"
            >
        </div>

        <x-filament-panels::header.simple
            :heading="$heading"
            :logo="false"
            :subheading="null"
        />

        {{ $this->content }}
    </div>

    @if (! $this instanceof \Filament\Tables\Contracts\HasTable)
        <x-filament-actions::modals />
    @endif

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_PAGE_END, scopes: $this->getRenderHookScopes()) }}
</div>
