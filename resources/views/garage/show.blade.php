@extends('layouts.public')

@php($primaryPhoto = $vehiclePhotos[0] ?? null)

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
            '@graph' => array_values(array_filter([
                [
                    '@type' => 'WebPage',
                    'name' => $metaTitle,
                    'url' => $canonicalUrl,
                    'description' => $metaDescription,
                    'inLanguage' => 'nl-NL',
                ],
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        [
                            '@type' => 'ListItem',
                            'position' => 1,
                            'name' => 'Home',
                            'item' => url('/'),
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 2,
                            'name' => $vehicleName,
                            'item' => $canonicalUrl,
                        ],
                    ],
                ],
                filled($vehicleHeading) ? [
                    '@type' => 'Vehicle',
                    'name' => $vehicleName,
                    'brand' => $vehicle->brand,
                    'image' => $primaryPhoto['url'] ?? null,
                    'model' => $vehicle->model,
                    'vehicleModelDate' => $vehicle->year ? (string) $vehicle->year : null,
                    'url' => $canonicalUrl,
                ] : null,
            ])),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
    </script>
@endsection

@section('content')
    <article style="max-width: 960px; margin: 0 auto; padding: 40px 24px 80px;">
        <nav aria-label="Breadcrumb" style="font-size: 14px; color: #4b5563; margin-bottom: 24px;">
            <a href="{{ url('/') }}" style="color: inherit;">Home</a>
            <span aria-hidden="true"> &gt; </span>
            <span>{{ $vehicleName }}</span>
        </nav>

        <header style="display: grid; gap: 24px; margin-bottom: 32px;">
            <div style="display: grid; gap: 12px;">
                <span style="display: inline-flex; width: fit-content; padding: 8px 12px; border-radius: 999px; background: #fff4bf; color: #111827; font-weight: 700;">
                    Publieke onderhoudshistorie
                </span>
                <h1 style="margin: 0; font-size: clamp(2rem, 4vw, 3.25rem); line-height: 1.05;">
                    Onderhoudshistorie van deze {{ $vehicleName }}
                </h1>
                <p style="margin: 0; font-size: 18px; color: #4b5563; max-width: 720px;">
                    Bekijk onderhoudsmomenten, kilometerstanden en uitgevoerde werkzaamheden van deze {{ $vehicleHeading }}.
                </p>
                <p style="margin: 0; font-size: 16px; color: #374151; max-width: 760px; line-height: 1.6;">
                    {{ $introText }}
                </p>
                @if(! $isIndexable)
                    <p style="margin: 0; font-size: 14px; color: #6b7280; max-width: 760px; line-height: 1.6;">
                        Deze pagina is publiek zichtbaar, maar nog niet bedoeld voor indexatie zolang de inhoud beperkt is.
                    </p>
                @endif
            </div>

            @if($vehiclePhotos !== [])
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
                    @foreach($vehiclePhotos as $photo)
                        <img
                            src="{{ $photo['thumbnail_url'] }}"
                            alt="{{ $vehicleName }}"
                            style="width: 100%; height: 220px; object-fit: cover; border-radius: 24px; background: #f3f4f6;"
                        >
                    @endforeach
                </div>
            @endif
        </header>

        <section style="display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 40px;">
            <div style="padding: 18px; border-radius: 20px; background: #f9fafb;">
                <div style="font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; color: #6b7280;">Voertuignaam</div>
                <div style="font-size: 20px; font-weight: 700; color: #111827;">{{ $vehicleName }}</div>
            </div>
            @if($vehicle->year)
                <div style="padding: 18px; border-radius: 20px; background: #f9fafb;">
                    <div style="font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; color: #6b7280;">Bouwjaar</div>
                    <div style="font-size: 20px; font-weight: 700; color: #111827;">{{ $vehicle->year }}</div>
                </div>
            @endif
            <div style="padding: 18px; border-radius: 20px; background: #f9fafb;">
                <div style="font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; color: #6b7280;">Merk</div>
                <div style="font-size: 20px; font-weight: 700; color: #111827;">{{ $vehicle->brand }}</div>
            </div>
            <div style="padding: 18px; border-radius: 20px; background: #f9fafb;">
                <div style="font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; color: #6b7280;">Model</div>
                <div style="font-size: 20px; font-weight: 700; color: #111827;">{{ $vehicle->model }}</div>
            </div>
            <div style="padding: 18px; border-radius: 20px; background: #f9fafb;">
                <div style="font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; color: #6b7280;">Onderhoudsmomenten</div>
                <div style="font-size: 20px; font-weight: 700; color: #111827;">{{ $publicStats['maintenance_count'] }}</div>
            </div>
            <div style="padding: 18px; border-radius: 20px; background: #f9fafb;">
                <div style="font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; color: #6b7280;">Laatst bijgewerkt</div>
                <div style="font-size: 20px; font-weight: 700; color: #111827;">{{ $publicStats['last_updated_label'] ?? 'Onbekend' }}</div>
            </div>
        </section>

        <section style="margin-bottom: 40px;">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px;">
                <h2 style="margin: 0; font-size: 28px;">Onderhoudstijdlijn</h2>
                <a
                    href="https://app.garagebook.nl/start"
                    style="display: inline-flex; align-items: center; justify-content: center; padding: 12px 18px; border-radius: 999px; background: #111827; color: #fff; text-decoration: none; font-weight: 700;"
                >
                    Maak gratis je eigen digitale onderhoudsboekje
                </a>
            </div>

            <div style="display: grid; gap: 20px;">
                @foreach($timelineItems as $item)
                    <article style="padding: 20px; border-radius: 24px; border: 1px solid #e5e7eb; background: #fff;">
                        <div style="display: grid; gap: 10px;">
                            <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                                @if($item['date_label'])
                                    <span style="display: inline-flex; padding: 6px 10px; border-radius: 999px; background: #f3f4f6; color: #374151; font-size: 14px;">
                                        Onderhoudsdatum: {{ $item['date_label'] }}
                                    </span>
                                @endif
                                @if($item['km_label'])
                                    <span style="display: inline-flex; padding: 6px 10px; border-radius: 999px; background: #f3f4f6; color: #374151; font-size: 14px;">
                                        Kilometerstand: {{ $item['km_label'] }}
                                    </span>
                                @endif
                                @if($item['cost_label'])
                                    <span style="display: inline-flex; padding: 6px 10px; border-radius: 999px; background: #fef3c7; color: #92400e; font-size: 14px;">
                                        Kosten: {{ $item['cost_label'] }}
                                    </span>
                                @endif
                            </div>
                            <h3 style="margin: 0; font-size: 20px; line-height: 1.25;">{{ $item['description'] !== '' ? $item['description'] : 'Onderhoudsmoment' }}</h3>
                            @if($item['notes'])
                                <p style="margin: 0; color: #374151; white-space: pre-line; line-height: 1.6;">{{ $item['notes'] }}</p>
                            @endif

                            @if($item['public_attachments'] !== [])
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px;">
                                    @foreach($item['public_attachments'] as $attachment)
                                        <a href="{{ $attachment['url'] }}" target="_blank" rel="noopener noreferrer">
                                            <img
                                                src="{{ $attachment['thumbnail_url'] }}"
                                                alt="{{ $attachment['alt'] }}"
                                                style="width: 100%; height: 160px; object-fit: cover; border-radius: 18px; background: #f3f4f6;"
                                            >
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section style="padding: 24px; border-radius: 24px; background: linear-gradient(135deg, #111827 0%, #1f2937 100%); color: #fff; margin-bottom: 32px;">
            <h2 style="margin: 0 0 12px; font-size: 26px;">Maak gratis je eigen digitale onderhoudsboekje</h2>
            <p style="margin: 0 0 18px; color: #e5e7eb; max-width: 680px;">
                Leg onderhoud, kilometerstanden, foto’s en belangrijke momenten vast in je eigen GarageBook-tijdlijn.
            </p>
            <a
                href="https://app.garagebook.nl/start"
                style="display: inline-flex; align-items: center; justify-content: center; padding: 12px 18px; border-radius: 999px; background: #ffd200; color: #111827; text-decoration: none; font-weight: 700;"
            >
                Maak gratis je eigen digitale onderhoudsboekje
            </a>
        </section>

        <section style="display: grid; gap: 12px;">
            <h2 style="margin: 0; font-size: 24px;">Ook je onderhoud digitaal bijhouden?</h2>
            <a href="https://garagebook.nl/digitaal-onderhoudsboekje/" style="color: #111827; font-weight: 700;">
                Lees meer over het digitale onderhoudsboekje van GarageBook
            </a>
            @if($typeSpecificLandingUrl)
                <a href="{{ $typeSpecificLandingUrl }}" style="color: #111827; font-weight: 700;">
                    Bekijk de onderhoudspagina die past bij dit voertuig
                </a>
            @endif
            <a href="https://app.garagebook.nl/start" style="color: #111827; font-weight: 700;">
                Maak gratis je eigen digitale onderhoudsboekje
            </a>
        </section>
    </article>
@endsection
