<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\HtmlString;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandLogo(asset('images/garagebook-logo.png'))
            ->brandLogoHeight('2.5rem')
            ->login()
            ->registration()
            ->passwordReset()
            ->defaultThemeMode(ThemeMode::Light)
            ->colors([
                'primary' => '#ffd200',
            ])
            ->pages([
                Dashboard::class,
            ])
            ->discoverResources(
                in: app_path('Filament/Resources'),
                for: 'App\\Filament\\Resources'
            )
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    public function boot(): void
    {
        FilamentView::registerRenderHook(
            'panels::head.end',
            fn (): string => new HtmlString('
                <style>
                    :root {
                        --gb-yellow: #ffd200;
                        --gb-black: #000000;
                    }

                    @font-face {
                        font-family: "Zalando Sans";
                        src: url("/fonts/zalandosans/ZalandoSans-Regular.woff2") format("woff2");
                        font-weight: 400;
                        font-style: normal;
                    }

                    @font-face {
                        font-family: "Zalando Sans";
                        src: url("/fonts/zalandosans/ZalandoSans-Bold.woff2") format("woff2");
                        font-weight: 700;
                        font-style: normal;
                    }

                    body,
                    .fi-layout,
                    .fi-main,
                    .fi-sidebar,
                    .fi-topbar,
                    .fi-page,
                    .fi-section,
                    .fi-input {
                        font-family: "Zalando Sans", sans-serif !important;
                    }

                    button[type="submit"],
                    a.fi-btn,
                    .fi-btn,
                    [data-color="primary"] {
                        background: var(--gb-yellow) !important;
                        border-color: var(--gb-yellow) !important;
                        color: var(--gb-black) !important;
                        font-family: "Zalando Sans", sans-serif !important;
                        font-weight: 700 !important;
                    }

                    button[type="submit"]:hover,
                    a.fi-btn:hover,
                    .fi-btn:hover,
                    [data-color="primary"]:hover {
                        background: var(--gb-yellow) !important;
                        border-color: var(--gb-yellow) !important;
                        color: var(--gb-black) !important;
                    }

                    /* FILAMENT SIDEBAR ACTIVE STATE */
                    .fi-sidebar-nav {
                        --c-400: 255, 210, 0 !important;
                        --c-500: 255, 210, 0 !important;
                        --c-600: 255, 210, 0 !important;
                        --c-700: 255, 210, 0 !important;
                    }

                    .fi-sidebar-nav a[aria-current="page"] {
                        background: #ffd200 !important;
                        color: #000 !important;
                    }

                    .fi-sidebar-nav a[aria-current="page"] * {
                        color: #000 !important;
                        fill: #000 !important;
                        stroke: #000 !important;
                    }

                    .fi-sidebar-nav a[aria-current="page"] svg {
                        color: #000 !important;
                        stroke: #000 !important;
                    }

                    input[type="checkbox"]:checked,
                    input[type="radio"]:checked {
                        accent-color: #ffd200 !important;
                    }
                </style>
            ')
        );
    }
}