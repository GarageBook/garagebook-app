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
                        width: min(100%, 780px);
                        min-width: 0;
                        margin-bottom: 85px;
                    }

                    .gb-footer-brand {
                        margin-bottom: 60px;
                    }

                    .gb-footer-logo {
                        height: 44px;
                    }

                    .gb-footer-columns {
                        display: grid;
                        grid-template-columns: 1fr 1fr 1fr;
                        gap: 70px;
                    }

                    .gb-footer-links a {
                        overflow-wrap: anywhere;
                    }

                    .gb-footer-bottom {
                        border-top: 1px solid #2c2f35;
                        padding-top: 28px;
                        font-size: 15px;
                        color: #8d9199;
                        width: 100%;
                    }

                    .gb-maintenance-attachments-upload .filepond--list-scroller,
                    .gb-maintenance-attachments-upload .filepond--list,
                    .gb-maintenance-attachments-upload .filepond--item,
                    .gb-maintenance-attachments-upload .filepond--item-panel,
                    .gb-maintenance-attachments-upload .filepond--file-wrapper,
                    .gb-maintenance-attachments-upload .filepond--file-info,
                    .gb-maintenance-attachments-upload .filepond--file {
                        display: none !important;
                        visibility: hidden !important;
                        height: 0 !important;
                        min-height: 0 !important;
                        max-height: 0 !important;
                        margin: 0 !important;
                        padding: 0 !important;
                        border: 0 !important;
                    }

                    .gb-maintenance-media-gallery {
                        margin-top: -4px;
                    }

                    .gb-maintenance-media-gallery__grid {
                        display: grid;
                        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
                        gap: 16px;
                    }

                    .gb-maintenance-media-gallery__card {
                        border: 1px solid #d1d5db;
                        border-radius: 14px;
                        overflow: hidden;
                        background: white;
                    }

                    .gb-maintenance-media-gallery__image,
                    .gb-maintenance-media-gallery__video {
                        display: block;
                        width: 100%;
                        height: 160px;
                        object-fit: cover;
                        background: #111827;
                    }

                    .gb-maintenance-media-gallery__meta {
                        display: flex;
                        align-items: flex-start;
                        justify-content: space-between;
                        gap: 12px;
                        padding: 12px;
                    }

                    .gb-maintenance-media-gallery__label {
                        min-width: 0;
                        font-size: 13px;
                        line-height: 1.4;
                        color: #111827;
                        overflow-wrap: anywhere;
                    }

                    .gb-maintenance-media-gallery__actions {
                        display: flex;
                        align-items: center;
                        gap: 10px;
                        flex-shrink: 0;
                    }

                    .gb-maintenance-media-gallery__link,
                    .gb-maintenance-media-gallery__remove {
                        border: 0;
                        background: transparent;
                        font-size: 13px;
                        font-weight: 700;
                        line-height: 1;
                        text-decoration: none;
                    }

                    .gb-maintenance-media-gallery__link {
                        color: #2563eb;
                    }

                    .gb-maintenance-media-gallery__remove {
                        color: #b91c1c;
                        cursor: pointer;
                    }

                    .gb-maintenance-media-gallery__file {
                        display: flex;
                        flex-direction: column;
                        justify-content: center;
                        gap: 10px;
                        min-height: 160px;
                        padding: 18px;
                        background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
                    }

                    .gb-maintenance-media-gallery__file-type {
                        align-self: flex-start;
                        padding: 6px 10px;
                        border-radius: 999px;
                        background: #dbeafe;
                        color: #1d4ed8;
                        font-size: 12px;
                        font-weight: 700;
                        letter-spacing: 0.04em;
                    }

                    .gb-maintenance-media-gallery__file-name {
                        font-size: 15px;
                        line-height: 1.5;
                        color: #111827;
                        overflow-wrap: anywhere;
                    }

                    @media (max-width: 1024px) {
                        .gb-footer-inner {
                            width: 100%;
                        }

                        .gb-footer-columns {
                            gap: 36px;
                        }
                    }

                    @media (max-width: 768px) {
                        .gb-login-footer {
                            padding: 48px 20px 32px;
                            box-sizing: border-box;
                        }

                        .gb-maintenance-media-gallery__grid {
                            grid-template-columns: 1fr 1fr;
                        }

                        .gb-footer-inner {
                            margin-bottom: 48px;
                        }

                        .gb-footer-brand {
                            margin-bottom: 32px;
                        }

                        .gb-footer-columns {
                            grid-template-columns: 1fr;
                            gap: 28px;
                        }

                        .gb-footer-links {
                            gap: 10px;
                            font-size: 16px;
                        }

                        .gb-footer-links a {
                            word-break: break-word;
                        }
                    }

                    @media (max-width: 480px) {
                        .gb-login-footer {
                            padding-left: 16px;
                            padding-right: 16px;
                        }

                        .gb-maintenance-media-gallery__grid {
                            grid-template-columns: 1fr;
                        }
                    }
                </style>
            ')
        );

        FilamentView::registerRenderHook(
            'panels::body.end',
            fn (): string => new HtmlString('
                <footer class="gb-login-footer">
                    <div class="gb-footer-inner">
                        <div class="gb-footer-brand">
                            <img src="/images/garagebook-logo-white.png" class="gb-footer-logo" alt="GarageBook">
                        </div>

                        <div class="gb-footer-columns">
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
                                    <a href="/">Website home</a>
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
