@extends('layouts.public')

@php
    $primaryPhoto = $vehiclePhotos[0] ?? null;
    $latestKmLabel = collect($timelineItems)->first(fn (array $item) => filled($item['km_label'] ?? null))['km_label'] ?? null;
    $visibleAttachmentCount = collect($timelineItems)->sum(fn (array $item): int => count($item['public_attachments'] ?? []));
    $metadataItems = array_values(array_filter([
        $vehicle->year ? (string) $vehicle->year : null,
        filled($vehicle->display_variant) ? $vehicle->display_variant : null,
        filled($vehicle->license_plate) ? 'Kenteken '.$vehicle->license_plate : null,
    ]));
@endphp

@section('title', $metaTitle)
@section('meta_description', $metaDescription)
@section('meta_robots', $metaRobots)
@section('canonical_url', $canonicalUrl)
@section('og_type', 'article')
@section('og_title', $metaTitle)
@section('og_description', $metaDescription)

@section('structured_data')
    <script type="application/ld+json">
        {!! json_encode([
            '@' . 'context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $metaTitle,
            'url' => $canonicalUrl,
            'description' => $metaDescription,
            'inLanguage' => 'nl-NL',
            'mainEntity' => filled($vehicleHeading) ? [
                '@type' => 'Vehicle',
                'name' => $vehicleName,
                'brand' => filled($vehicle->brand) ? [
                    '@type' => 'Brand',
                    'name' => $vehicle->brand,
                ] : null,
                'image' => $primaryPhoto['url'] ?? null,
                'model' => $vehicle->model,
                'vehicleModelDate' => $vehicle->year ? (string) $vehicle->year : null,
                'url' => $canonicalUrl,
            ] : null,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
    </script>
@endsection

@section('content')
    <style>
        .gb-public-header { display: none !important; }

        .gb-v2-page {
            min-height: 100vh;
            background: #f4f5f7;
            color: #0f172a;
        }

        .gb-v2-container {
            width: 100%;
            max-width: 1280px;
            margin: 0 auto;
            padding-left: 16px;
            padding-right: 16px;
            box-sizing: border-box;
        }

        .gb-v2-header {
            background: #020617;
            color: #ffffff;
        }

        .gb-v2-header__inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding-top: 16px;
            padding-bottom: 16px;
        }

        .gb-v2-logo {
            display: block;
            max-height: 40px;
            width: auto;
        }

        .gb-v2-nav {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 14px;
            line-height: 1.2;
        }

        .gb-v2-nav a {
            color: rgba(255, 255, 255, 0.78);
            text-decoration: none;
        }

        .gb-v2-nav a:hover { color: #ffffff; }

        .gb-v2-main {
            padding-top: 32px;
            padding-bottom: 48px;
        }

        .gb-v2-stack { display: grid; gap: 28px; }

        .gb-v2-hero {
            display: grid;
            gap: 24px;
            align-items: start;
        }

        .gb-v2-hero__media {
            width: 100%;
            max-width: 360px;
        }

        .gb-v2-hero__image,
        .gb-v2-hero__empty {
            display: block;
            width: 100%;
            aspect-ratio: 4 / 3;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }

        .gb-v2-hero__image {
            object-fit: cover;
        }

        .gb-v2-hero__empty {
            display: grid;
            place-items: center;
            padding: 24px;
            color: #64748b;
            text-align: center;
            font-size: 14px;
        }

        .gb-v2-hero__text {
            min-width: 0;
            display: grid;
            gap: 12px;
        }

        .gb-v2-eyebrow {
            margin: 0;
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .gb-v2-title {
            margin: 0;
            max-width: 760px;
            color: #0f172a;
            font-size: 30px;
            line-height: 1.15;
            font-weight: 700;
            letter-spacing: 0;
        }

        .gb-v2-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 4px 10px;
            color: #475569;
            font-size: 14px;
            line-height: 1.5;
        }

        .gb-v2-meta__sep { color: #cbd5e1; }

        .gb-v2-intro {
            margin: 0;
            max-width: 620px;
            color: #475569;
            font-size: 16px;
            line-height: 1.65;
        }

        .gb-v2-summary {
            display: grid;
            gap: 12px;
        }

        .gb-v2-summary-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #ffffff;
            padding: 16px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
        }

        .gb-v2-summary-card p {
            margin: 0;
        }

        .gb-v2-summary-card__label {
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .gb-v2-summary-card__value {
            margin-top: 6px !important;
            color: #0f172a;
            font-size: 24px;
            line-height: 1;
            font-weight: 700;
        }

        .gb-v2-section-title {
            margin: 0 0 14px;
            color: #0f172a;
            font-size: 24px;
            line-height: 1.2;
            font-weight: 700;
            letter-spacing: 0;
        }

        .gb-v2-timeline {
            display: grid;
            gap: 14px;
        }

        .gb-v2-log {
            display: grid;
            gap: 16px;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: #ffffff;
            padding: 16px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
        }

        .gb-v2-log__meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            color: #475569;
            font-size: 14px;
        }

        .gb-v2-pill {
            display: inline-flex;
            border-radius: 999px;
            background: #f1f5f9;
            padding: 4px 10px;
            line-height: 1.4;
        }

        .gb-v2-pill--strong {
            color: #0f172a;
            font-weight: 700;
        }

        .gb-v2-pill--cost {
            background: #fef3c7;
            color: #92400e;
        }

        .gb-v2-log__body {
            min-width: 0;
            display: grid;
            gap: 10px;
        }

        .gb-v2-log__title {
            margin: 0;
            color: #0f172a;
            font-size: 18px;
            line-height: 1.35;
            font-weight: 700;
            letter-spacing: 0;
        }

        .gb-v2-log__notes {
            margin: 0;
            max-width: 680px;
            color: #475569;
            font-size: 14px;
            line-height: 1.6;
            white-space: pre-line;
        }

        .gb-v2-docs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .gb-v2-doc {
            display: inline-flex;
            align-items: center;
            max-width: 100%;
            gap: 8px;
            border: 1px solid #e2e8f0;
            border-radius: 999px;
            background: #f8fafc;
            color: #334155;
            padding: 6px 10px;
            text-decoration: none;
            font-size: 12px;
            line-height: 1.3;
        }

        .gb-v2-doc strong {
            text-transform: uppercase;
        }

        .gb-v2-doc span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .gb-v2-photos {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }

        .gb-v2-photo {
            display: block;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f1f5f9;
        }

        .gb-v2-photo img {
            display: block;
            width: 100%;
            height: 96px;
            object-fit: cover;
        }

        .gb-v2-no-photo {
            margin: 0;
            border: 1px dashed #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
            color: #64748b;
            padding: 12px;
            font-size: 14px;
            line-height: 1.5;
        }

        .gb-login-footer {
            margin-top: 0 !important;
            background: #020617 !important;
            padding: 34px 16px 26px !important;
        }

        .gb-footer-inner {
            width: 100% !important;
            min-width: 0 !important;
            max-width: 1280px !important;
            margin: 0 auto 28px !important;
        }

        .gb-footer-brand {
            margin-bottom: 20px !important;
        }

        .gb-footer-logo {
            max-height: 34px !important;
            width: auto !important;
        }

        .gb-footer-columns {
            display: grid !important;
            grid-template-columns: 1fr !important;
            gap: 22px !important;
            align-items: start !important;
        }

        .gb-login-footer h3 {
            margin: 0 0 10px !important;
            font-size: 14px !important;
            line-height: 1.3 !important;
        }

        .gb-footer-links {
            gap: 6px !important;
            font-size: 14px !important;
        }

        .gb-footer-links a {
            color: rgba(255, 255, 255, 0.72) !important;
            font-size: 14px !important;
            line-height: 1.45 !important;
        }

        .gb-footer-bottom {
            padding-top: 18px !important;
            color: rgba(255, 255, 255, 0.56) !important;
            font-size: 13px !important;
        }

        @media (min-width: 640px) {
            .gb-v2-container {
                padding-left: 24px;
                padding-right: 24px;
            }

            .gb-v2-summary {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .gb-v2-photos {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (min-width: 1024px) {
            .gb-v2-container {
                padding-left: 32px;
                padding-right: 32px;
            }

            .gb-v2-main {
                padding-top: 40px;
            }

            .gb-v2-hero {
                grid-template-columns: 360px minmax(0, 1fr);
                gap: 32px;
            }

            .gb-v2-title {
                font-size: 36px;
            }

            .gb-v2-log {
                grid-template-columns: 140px minmax(0, 1fr) 360px;
                align-items: start;
                padding: 20px;
            }

            .gb-v2-log__meta {
                display: grid;
                gap: 8px;
            }

            .gb-v2-photo img {
                height: 96px;
            }

            .gb-footer-columns {
                grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
                gap: 32px !important;
            }
        }
    </style>

    <div class="gb-v2-page">
        <header class="gb-v2-header">
            <div class="gb-v2-container gb-v2-header__inner">
                <a href="{{ url('/') }}" aria-label="GarageBook home">
                    <img src="{{ asset('images/garagebook-logo-white.png') }}" alt="GarageBook" class="gb-v2-logo">
                </a>

                <nav class="gb-v2-nav" aria-label="GarageBook navigatie">
                    <a href="{{ url('/') }}">Website home</a>
                    <a href="{{ url('/contact') }}">Contact</a>
                </nav>
            </div>
        </header>

        <main class="gb-v2-container gb-v2-main">
            <article class="gb-v2-stack">
                <section class="gb-v2-hero">
                    <div class="gb-v2-hero__media">
                        @if($primaryPhoto)
                            <img
                                src="{{ $primaryPhoto['thumbnail_url'] }}"
                                alt="{{ $vehicleName }}"
                                class="gb-v2-hero__image"
                                loading="eager"
                                decoding="async"
                            >
                        @else
                            <div class="gb-v2-hero__empty">
                                Nog geen publieke voertuigfoto zichtbaar
                            </div>
                        @endif
                    </div>

                    <div class="gb-v2-hero__text">
                        <p class="gb-v2-eyebrow">Gedeelde voertuiggeschiedenis</p>
                        <h1 class="gb-v2-title">{{ $vehicleName }}</h1>

                        @if($metadataItems !== [])
                            <div class="gb-v2-meta">
                                @foreach($metadataItems as $metadataItem)
                                    <span>{{ $metadataItem }}</span>
                                    @if(! $loop->last)
                                        <span aria-hidden="true" class="gb-v2-meta__sep">/</span>
                                    @endif
                                @endforeach
                            </div>
                        @endif

                        <p class="gb-v2-intro">
                            Een gedeelde onderhoudshistorie met vastgelegde werkzaamheden, kilometerstanden en bijlagen.
                        </p>
                    </div>
                </section>

                <section aria-label="Samenvatting" class="gb-v2-summary">
                    <div class="gb-v2-summary-card">
                        <p class="gb-v2-summary-card__label">Onderhoudsmomenten</p>
                        <p class="gb-v2-summary-card__value">{{ $publicStats['maintenance_count'] }}</p>
                    </div>

                    <div class="gb-v2-summary-card">
                        <p class="gb-v2-summary-card__label">Laatste kilometerstand</p>
                        <p class="gb-v2-summary-card__value">{{ $latestKmLabel ?? 'Onbekend' }}</p>
                    </div>

                    <div class="gb-v2-summary-card">
                        <p class="gb-v2-summary-card__label">Bijlagen</p>
                        <p class="gb-v2-summary-card__value">{{ $visibleAttachmentCount }}</p>
                    </div>
                </section>

                <section aria-labelledby="maintenance-timeline">
                    <h2 id="maintenance-timeline" class="gb-v2-section-title">Onderhoudshistorie</h2>

                    <div class="gb-v2-timeline">
                        @forelse($timelineItems as $item)
                            <article class="gb-v2-log">
                                <div class="gb-v2-log__meta">
                                    @if($item['date_label'])
                                        <time class="gb-v2-pill gb-v2-pill--strong">{{ $item['date_label'] }}</time>
                                    @endif
                                    @if($item['km_label'])
                                        <span class="gb-v2-pill">{{ $item['km_label'] }}</span>
                                    @endif
                                    @if($item['cost_label'])
                                        <span class="gb-v2-pill gb-v2-pill--cost">{{ $item['cost_label'] }}</span>
                                    @endif
                                </div>

                                <div class="gb-v2-log__body">
                                    <h3 class="gb-v2-log__title">
                                        {{ $item['description'] !== '' ? $item['description'] : 'Onderhoudsmoment' }}
                                    </h3>

                                    @if($item['notes'])
                                        <p class="gb-v2-log__notes">{{ $item['notes'] }}</p>
                                    @endif

                                    @if($item['public_other_attachments'] !== [])
                                        <div class="gb-v2-docs">
                                            @foreach($item['public_other_attachments'] as $attachment)
                                                <a
                                                    href="{{ $attachment['url'] }}"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="gb-v2-doc"
                                                >
                                                    <strong>{{ $attachment['kind'] ?? 'file' }}</strong>
                                                    <span>{{ $attachment['label'] ?? 'Bijlage' }}</span>
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                <div>
                                    @if($item['public_image_attachments'] !== [])
                                        <div class="gb-v2-photos">
                                            @foreach($item['public_image_attachments'] as $attachment)
                                                <a href="{{ $attachment['url'] }}" target="_blank" rel="noopener noreferrer" class="gb-v2-photo">
                                                    <img
                                                        src="{{ $attachment['thumbnail_url'] }}"
                                                        alt="{{ $attachment['alt'] }}"
                                                        loading="lazy"
                                                        decoding="async"
                                                    >
                                                </a>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="gb-v2-no-photo">Geen publieke foto zichtbaar.</p>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <article class="gb-v2-log">
                                <div class="gb-v2-log__body">
                                    <h3 class="gb-v2-log__title">Nog geen publiek onderhoud gedeeld</h3>
                                    <p class="gb-v2-log__notes">Zodra onderhoud wordt vastgelegd, verschijnt hier de gedeelde onderhoudshistorie.</p>
                                </div>
                            </article>
                        @endforelse
                    </div>
                </section>
            </article>
        </main>
    </div>
@endsection
