@extends('layouts.public')

@php
    $pageTitle = "{$brand} {$model} onderhoud – bijhouden en documenteren | GarageBook";
    $pageDescription = "Houd het onderhoud van je {$brand} {$model} digitaal bij met GarageBook. Beurten, facturen, kilometerstanden en foto's op één plek. Overdraagbaar bij verkoop.";
    $canonicalUrl = url('/onderhoud/' . $slug);
    $faqGraph = $faq_items ? [
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn ($item) => [
            '@type' => 'Question',
            'name' => $item['question'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $item['answer'],
            ],
        ], $faq_items),
    ] : null;
    $graph = array_values(array_filter([
        [
            '@type' => 'Organization',
            'name' => 'GarageBook',
            'url' => url('/'),
            'logo' => asset('images/garagebook-logo.png'),
        ],
        [
            '@type' => 'WebPage',
            'name' => $pageTitle,
            'description' => $pageDescription,
            'url' => $canonicalUrl,
            'inLanguage' => 'nl-NL',
            'breadcrumb' => [
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
                        'name' => 'Onderhoud',
                        'item' => url('/onderhoud'),
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 3,
                        'name' => "{$brand} {$model}",
                        'item' => $canonicalUrl,
                    ],
                ],
            ],
        ],
        $faqGraph,
    ]));
@endphp

@section('title', $pageTitle)
@section('meta_description', $pageDescription)
@section('meta_robots', 'index,follow')
@section('canonical_url', $canonicalUrl)
@section('og_title', $pageTitle)
@section('og_description', $pageDescription)

@section('structured_data')
<script type="application/ld+json">
{!! json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
</script>
@endsection

@section('content')
<article class="gb-content-shell">

    {{-- Breadcrumbs --}}
    <nav class="gb-breadcrumbs" aria-label="Breadcrumb">
        <a href="{{ url('/') }}" class="gb-breadcrumbs__link">Home</a>
        <span class="gb-breadcrumbs__sep" aria-hidden="true">&rsaquo;</span>
        <span class="gb-breadcrumbs__current">{{ $brand }} {{ $model }}</span>
    </nav>

    {{-- H1 --}}
    <h1 class="gb-page-title">
        {{ $brand }} {{ $model }} onderhoud bijhouden
    </h1>

    {{-- Intro --}}
    <p class="gb-page-intro">
        Bouw een complete digitale onderhoudshistorie op van je {{ $brand }} {{ $model }}. Leg beurten, reparaties, kilometerstanden, foto's en facturen vast op één plek – altijd beschikbaar en overdraagbaar bij verkoop.
        @if($year_range)
            Momenteel staan bouwjaren {{ $year_range }} in GarageBook geregistreerd.
        @endif
    </p>

    <div class="gb-page-content">

        {{-- Voertuigbeschrijving --}}
        <h2>Over de {{ $brand }} {{ $model }}</h2>
        <p>
            De {{ $brand }} {{ $model }} is een voertuig waarvoor eigenaren via GarageBook een digitale onderhoudshistorie bijhouden. Of je nu zelf sleutelt of alles bij de garage laat doen: GarageBook geeft je één centrale plek voor alle werkzaamheden, kilomsterstanden en bewijsdocumenten.
        </p>

        {{-- Onderhoudsadvies --}}
        <h2>Onderhoud bijhouden voor je {{ $brand }} {{ $model }}</h2>
        <p>
            Een goede onderhoudshistorie begint bij het consequent vastleggen van iedere beurt. Noteer minimaal datum, kilometerstand, uitgevoerde werkzaamheden en gebruikte onderdelen. Voeg facturen en foto's toe als bewijs. Doe dit ook voor zelf uitgevoerd onderhoud.
        </p>
        <ul>
            <li>Periodieke beurten: olie, filters, vloeistoffen</li>
            <li>Remmen: blokken, schijven en vloeistof</li>
            <li>Banden: merk, maat en datum</li>
            <li>APK-keuringen en eventuele opmerkingen</li>
            <li>Reparaties, vervangen onderdelen en upgrades</li>
        </ul>
        <p>
            Raadpleeg altijd de instructies van {{ $brand }} voor de aanbevolen onderhoudsmomenten en intervallen specifiek voor de {{ $model }}.
        </p>

        {{-- Onderhoudslogs uitleg --}}
        <h2>Hoe GarageBook werkt voor je {{ $brand }} {{ $model }}</h2>
        <ol>
            <li>Registreer gratis via <a href="/start">app.garagebook.nl/start</a></li>
            <li>Voeg de {{ $brand }} {{ $model }} toe als voertuig</li>
            <li>Voer bestaande beurten in als starthistorie</li>
            <li>Log nieuwe werkzaamheden direct na uitvoering</li>
            <li>Upload facturen en foto's als bewijs</li>
        </ol>

        {{-- CTA --}}
        <p>
            <a href="/start" class="gb-button gb-button--primary">Start gratis met GarageBook</a>
        </p>

        {{-- Openbare GarageBook-voertuigen --}}
        @if($public_vehicles->isNotEmpty())
        <h2>Openbare onderhoudsgeschiedenissen van de {{ $brand }} {{ $model }}</h2>
        <p>
            Hieronder vind je openbare GarageBook-pagina's van eigenaren die hun {{ $brand }} {{ $model }} hebben gedocumenteerd. Zo zie je hoe anderen hun onderhoudshistorie bijhouden.
        </p>
        <div class="gb-vehicle-authority-list">
            @foreach($public_vehicles as $vehicle)
            <a href="{{ url('/garage/' . $vehicle->public_slug) }}" class="gb-vehicle-authority-item">
                <span class="gb-vehicle-authority-item__name">
                    {{ $vehicle->year ? $vehicle->year . ' ' : '' }}{{ $vehicle->brand }} {{ $vehicle->model }}
                    @if($vehicle->display_variant)
                        <span class="gb-vehicle-authority-item__variant">{{ $vehicle->display_variant }}</span>
                    @endif
                </span>
                <span class="gb-vehicle-authority-item__count">
                    {{ $vehicle->maintenanceLogs->count() }} onderhoudsmoment(en)
                </span>
            </a>
            @endforeach
        </div>
        @endif

        {{-- Gerelateerde voertuigen --}}
        @if($related_models->isNotEmpty())
        <h2>Andere {{ $brand }}-modellen in GarageBook</h2>
        <div class="gb-vehicle-authority-related">
            @foreach($related_models as $related)
            <a href="{{ url('/onderhoud/' . $related['slug']) }}" class="gb-vehicle-authority-related__item">
                {{ $related['brand'] }} {{ $related['model'] }}
            </a>
            @endforeach
        </div>
        @endif

        {{-- FAQ --}}
        <h2>Veelgestelde vragen over {{ $brand }} {{ $model }} onderhoud</h2>
        <div class="gb-faq">
            @foreach($faq_items as $faqItem)
            <div class="gb-faq__item">
                <h3 class="gb-faq__question">{{ $faqItem['question'] }}</h3>
                <p class="gb-faq__answer">{{ $faqItem['answer'] }}</p>
            </div>
            @endforeach
        </div>

    </div>

    {{-- Gerelateerde artikelen --}}
    <aside class="gb-related-content">
        <h2 class="gb-related-content__title">Gerelateerde artikelen</h2>
        <div class="gb-related-content__items">
            @foreach($related_pages as $relatedPage)
            <a href="{{ $relatedPage['url'] }}" class="gb-related-content__item">
                <span class="gb-related-content__label">Artikel</span>
                <strong>{{ $relatedPage['title'] }}</strong>
                <span class="gb-related-content__desc">{{ $relatedPage['description'] }}</span>
            </a>
            @endforeach
        </div>
    </aside>

</article>
@endsection
