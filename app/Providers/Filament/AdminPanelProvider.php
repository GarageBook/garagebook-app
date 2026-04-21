<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\BlogResource; // 👈 TOEGEVOEGD
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
            ->brandName('GarageBook')
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
            ->resources([ // 👈 TOEGEVOEGD (BELANGRIJK)
                BlogResource::class,
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
                    .gb-login-footer {
                        background: #0d0f12;
                        color: white;
                        font-family: "Zalando Sans", sans-serif;
                        padding: 70px 50px 40px 50px;
                        width: 100%;
                    }

                    .gb-login-footer a {
                        color: #8d9199;
                        text-decoration: none;
                    }

                    .gb-login-footer a:hover {
                        color: white;
                    }

                    .gb-login-footer h3 {
                        font-size: 18px;
                        font-weight: 700;
                        margin-bottom: 22px;
                    }

                    .gb-footer-links {
                        display: flex;
                        flex-direction: column;
                        gap: 14px;
                        font-size: 17px;
                    }

                    .gb-footer-inner {
                        width: 52%;
                        min-width: 780px;
                        margin-bottom: 85px;
                    }

                    .gb-footer-bottom {
                        border-top: 1px solid #2c2f35;
                        padding-top: 28px;
                        font-size: 15px;
                        color: #8d9199;
                        width: 100%;
                    }
                </style>
            ')
        );

        FilamentView::registerRenderHook(
            'panels::body.end',
            fn (): string => new HtmlString('
                <footer class="gb-login-footer">
                    <div class="gb-footer-inner">
                        <div style="margin-bottom:60px;">
                            <img src="/images/garagebook-logo-white.png" style="height:44px;" alt="GarageBook">
                        </div>

                        <div style="
                            display:grid;
                            grid-template-columns: 1fr 1fr 1fr;
                            gap:70px;
                        ">
                            <div>
                                <h3>Mijn GarageBook</h3>
                                <div class="gb-footer-links">
                                    <a href="/admin/vehicles">Mijn voertuigen</a>
                                    <a href="/admin/maintenance-logs">Onderhoud</a>
                                </div>
                            </div>

                            <div>
                                <h3>Over GarageBook</h3>
                                <div class="gb-footer-links">
                                    <a href="/blogs">Blogs</a>
                                    <a href="/ons-verhaal">Ons verhaal</a>
                                    <a href="/privacy-statement">Privacy Statement</a>
                                    <a href="/algemene-voorwaarden">Voorwaarden</a>
                                    <a href="/contact">Contact</a>
                                </div>
                            </div>

                            <div>
                                <h3>Volg ons op social media</h3>
                                <div class="gb-footer-links">
                                <a href="https://www.instagram.com/garagebook.global" target="_blank">Instagram</a>
                                <a href="https://linkedin.com/company/thegaragebook/" target="_blank">LinkedIn</a>
                                <a href="https://www.facebook.com/profile.php?id=61584164445375" target="_blank">Facebook</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="gb-footer-bottom">
                        © GarageBook 2026 - Alle rechten voorbehouden
                    </div>
                </footer>
            ')
        );
    }
}
