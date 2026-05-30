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
    <article style="max-width:1120px; margin:0 auto; padding:clamp(24px, 6vw, 40px) clamp(16px, 5vw, 24px) 88px; min-width:0; overflow-wrap:normal;">
        <nav aria-label="Breadcrumb" style="font-size:14px; color:#4b5563; margin-bottom:24px; overflow-wrap:normal;">
            <a href="{{ url('/') }}" style="color:inherit;">Home</a>
            <span aria-hidden="true"> &gt; </span>
            <span>{{ $vehicleName }}</span>
        </nav>

        <header style="display:grid; gap:24px; margin-bottom:32px;">
            <div style="display:grid; gap:18px; padding:clamp(20px, 5vw, 32px); border-radius:clamp(24px, 6vw, 32px); background:linear-gradient(140deg, #fff9dd 0%, #ffffff 46%, #eef4ff 100%); border:1px solid #e5e7eb; box-shadow:0 20px 60px rgba(15, 23, 42, 0.06);">
                <div style="display:flex; flex-wrap:wrap; gap:12px; align-items:center; justify-content:space-between; min-width:0;">
                    <span style="display:inline-flex; max-width:100%; width:fit-content; padding:8px 12px; border-radius:999px; background:#fff1a8; color:#111827; font-weight:700;">
                        Gedeeld door eigenaar
                    </span>
                    <span style="display:inline-flex; max-width:100%; width:fit-content; padding:8px 12px; border-radius:999px; background:#eef2ff; color:#3730a3; font-weight:600;">
                        Eigenaar houdt controle over wat openbaar is
                    </span>
                </div>

                <div style="display:grid; gap:14px; max-width:820px; min-width:0;">
                    <h1 style="margin:0; font-size:clamp(1.9rem, 10vw, 3.6rem); line-height:1.02; letter-spacing:-0.03em; color:#111827;">
                        Aantoonbare voertuiggeschiedenis van deze {{ $vehicleName }}
                    </h1>
                    <p style="margin:0; font-size:19px; color:#374151; max-width:780px; line-height:1.65;">
                        Onderhoud, kilometerstanden, foto's en bewijsstukken laten zien wat er aan dit voertuig is vastgelegd en gedeeld.
                    </p>
                    <p style="margin:0; font-size:16px; color:#374151; max-width:780px; line-height:1.7;">
                        {{ $introText }}
                    </p>
                    <p style="margin:0; font-size:15px; color:#4b5563; max-width:780px; line-height:1.7;">
                        {{ $verificationNote }}
                    </p>
                    @if(! $isIndexable)
                        <p style="margin:0; font-size:14px; color:#6b7280; max-width:780px; line-height:1.6;">
                            Deze pagina is publiek zichtbaar, maar nog niet bedoeld voor indexatie zolang de gedeelde historie beperkt is.
                        </p>
                    @endif
                </div>

                <div style="display:flex; flex-wrap:wrap; gap:12px; min-width:0;">
                    <a
                        href="https://app.garagebook.nl/start"
                        style="display:inline-flex; align-items:center; justify-content:center; padding:12px 18px; border-radius:999px; background:#111827; color:#fff; text-decoration:none; font-weight:700; max-width:100%; text-align:center;"
                    >
                        Bouw je eigen voertuiggeschiedenis
                    </a>
                    <a
                        href="https://garagebook.nl/digitaal-onderhoudsboekje/"
                        style="display:inline-flex; align-items:center; justify-content:center; padding:12px 18px; border-radius:999px; background:#fff; color:#111827; text-decoration:none; font-weight:700; border:1px solid #d1d5db; max-width:100%; text-align:center;"
                    >
                        Lees meer over digitaal onderhoud
                    </a>
                </div>
            </div>

            @if($vehiclePhotos !== [])
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(min(100%, 220px), 1fr)); gap:16px;">
                    @foreach($vehiclePhotos as $photo)
                        <img
                            src="{{ $photo['thumbnail_url'] }}"
                            alt="{{ $vehicleName }}"
                            style="width:100%; height:240px; object-fit:cover; border-radius:24px; background:#f3f4f6;"
                        >
                    @endforeach
                </div>
            @else
                <section style="padding:22px; border-radius:24px; background:#f9fafb; border:1px dashed #d1d5db; color:#374151;">
                    <h2 style="margin:0 0 8px; font-size:22px; color:#111827;">Nog geen publieke voertuigfoto's zichtbaar</h2>
                    <p style="margin:0; line-height:1.7;">
                        Deze historie kan al wel gedeeld worden. De eigenaar bepaalt zelf of later ook foto's of extra bewijsbeelden openbaar worden gemaakt.
                    </p>
                </section>
            @endif
        </header>

        <section style="display:grid; gap:16px; grid-template-columns:repeat(auto-fit, minmax(min(100%, 180px), 1fr)); margin-bottom:24px;">
            <div style="padding:18px; border-radius:20px; background:#f9fafb;">
                <div style="font-size:12px; letter-spacing:0.08em; text-transform:uppercase; color:#6b7280;">Voertuig</div>
                <div style="font-size:20px; font-weight:700; color:#111827;">{{ $vehicleName }}</div>
            </div>
            <div style="padding:18px; border-radius:20px; background:#f9fafb;">
                <div style="font-size:12px; letter-spacing:0.08em; text-transform:uppercase; color:#6b7280;">Onderhoudsmomenten</div>
                <div style="font-size:20px; font-weight:700; color:#111827;">{{ $publicStats['maintenance_count'] }}</div>
            </div>
            <div style="padding:18px; border-radius:20px; background:#f9fafb;">
                <div style="font-size:12px; letter-spacing:0.08em; text-transform:uppercase; color:#6b7280;">Historieperiode</div>
                <div style="font-size:20px; font-weight:700; color:#111827;">{{ $publicStats['history_period_label'] }}</div>
            </div>
            <div style="padding:18px; border-radius:20px; background:#f9fafb;">
                <div style="font-size:12px; letter-spacing:0.08em; text-transform:uppercase; color:#6b7280;">Met datum en kilometerstand</div>
                <div style="font-size:20px; font-weight:700; color:#111827;">{{ $publicStats['documented_km_count'] }} moment{{ $publicStats['documented_km_count'] === 1 ? '' : 'en' }}</div>
            </div>
            <div style="padding:18px; border-radius:20px; background:#f9fafb;">
                <div style="font-size:12px; letter-spacing:0.08em; text-transform:uppercase; color:#6b7280;">Foto's & bewijs</div>
                <div style="font-size:20px; font-weight:700; color:#111827;">{{ $publicStats['visible_photo_count'] }} zichtbaar</div>
            </div>
            <div style="padding:18px; border-radius:20px; background:#f9fafb;">
                <div style="font-size:12px; letter-spacing:0.08em; text-transform:uppercase; color:#6b7280;">Kosten inzicht</div>
                <div style="font-size:20px; font-weight:700; color:#111827;">
                    @if($publicStats['shared_costs_enabled'])
                        {{ $publicStats['shared_cost_count'] > 0 ? $publicStats['shared_cost_count'] . ' gedeeld' : 'Nog niet zichtbaar' }}
                    @else
                        Privé gehouden
                    @endif
                </div>
            </div>
            <div style="padding:18px; border-radius:20px; background:#f9fafb;">
                <div style="font-size:12px; letter-spacing:0.08em; text-transform:uppercase; color:#6b7280;">Privacy</div>
                <div style="font-size:20px; font-weight:700; color:#111827;">Eigenaar bepaalt wat openbaar is</div>
            </div>
            <div style="padding:18px; border-radius:20px; background:#f9fafb;">
                <div style="font-size:12px; letter-spacing:0.08em; text-transform:uppercase; color:#6b7280;">Laatst bijgewerkt</div>
                <div style="font-size:20px; font-weight:700; color:#111827;">{{ $publicStats['last_updated_label'] ?? 'Onbekend' }}</div>
            </div>
        </section>

        <section style="display:grid; gap:16px; grid-template-columns:repeat(auto-fit, minmax(min(100%, 220px), 1fr)); margin-bottom:40px;">
            @foreach($historyHighlights as $highlight)
                <article style="padding:20px; border-radius:24px; border:1px solid #e5e7eb; background:#fff; box-shadow:0 16px 40px rgba(15, 23, 42, 0.04);">
                    <div style="font-size:12px; letter-spacing:0.08em; text-transform:uppercase; color:#6b7280; margin-bottom:10px;">{{ $highlight['label'] }}</div>
                    <div style="font-size:18px; line-height:1.55; color:#111827; font-weight:600;">{{ $highlight['value'] }}</div>
                </article>
            @endforeach
        </section>

        <section style="display:grid; grid-template-columns:repeat(auto-fit, minmax(min(100%, 280px), 1fr)); gap:20px; margin-bottom:40px;">
            <article style="padding:24px; border-radius:28px; background:#fff; border:1px solid #e5e7eb;">
                <h2 style="margin:0 0 12px; font-size:clamp(1.45rem, 7vw, 1.75rem); color:#111827; overflow-wrap:normal;">Waarom deze historie vertrouwen opbouwt</h2>
                <p style="margin:0 0 12px; color:#374151; line-height:1.7;">
                    Een sterke voertuigpagina laat niet alleen zien dat er onderhoud is gedaan, maar ook hoe consequent het is vastgelegd. Dat maakt deze historie bruikbaar voor verkoop, waardebehoud en gesprekken met een garage.
                </p>
                <p style="margin:0; color:#4b5563; line-height:1.7;">
                    Een complete historie helpt vertrouwen opbouwen bij verkoop. Kilometerstanden, onderhoudsdata, foto's en gedeelde bewijsstukken maken sneller duidelijk wat er aantoonbaar is gebeurd.
                </p>
            </article>

            <aside style="padding:24px; border-radius:28px; background:linear-gradient(135deg, #111827 0%, #1f2937 100%); color:#fff;">
                <div style="font-size:12px; letter-spacing:0.14em; text-transform:uppercase; color:rgba(255,255,255,0.62); margin-bottom:12px;">{{ $shareCues['eyebrow'] }}</div>
                <h2 style="margin:0 0 10px; font-size:clamp(1.35rem, 7vw, 1.625rem); line-height:1.08; overflow-wrap:normal;">{{ $shareCues['title'] }}</h2>
                <p style="margin:0 0 12px; color:#e5e7eb; line-height:1.7;">{{ $shareCues['description'] }}</p>
                <p style="margin:0 0 12px; color:#f9fafb; line-height:1.6; font-weight:600;">{{ $shareCues['audience'] }}</p>
                <p style="margin:0 0 12px; color:#d1d5db; line-height:1.65; font-size:14px;">Deel deze onderhoudsgeschiedenis met een koper, garage of liefhebber wanneer je wilt.</p>
                <p style="margin:0; color:#d1d5db; line-height:1.65; font-size:14px;">{{ $shareCues['future_transfer_note'] }}</p>
            </aside>
        </section>

        <section style="margin-bottom:40px;">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:16px; flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0; font-size:clamp(1.45rem, 7vw, 1.75rem); color:#111827; overflow-wrap:normal;">Onderhoudstijdlijn</h2>
                    <p style="margin:8px 0 0; color:#4b5563; line-height:1.6;">Een overzicht van uitgevoerde werkzaamheden, vastgelegde kilometerstanden en zichtbaar onderhoudsbewijs.</p>
                </div>
                <a
                    href="https://app.garagebook.nl/start"
                    style="display:inline-flex; align-items:center; justify-content:center; padding:12px 18px; border-radius:999px; background:#111827; color:#fff; text-decoration:none; font-weight:700; max-width:100%; text-align:center;"
                >
                    Bouw je eigen voertuiggeschiedenis
                </a>
            </div>

            <div style="display:grid; gap:20px;">
                @forelse($timelineItems as $item)
                    <article style="padding:clamp(18px, 5vw, 22px); border-radius:24px; border:1px solid #e5e7eb; background:#fff; box-shadow:0 16px 36px rgba(15, 23, 42, 0.04); min-width:0; overflow-wrap:normal;">
                        <div style="display:grid; gap:12px;">
                            <div style="display:flex; flex-wrap:wrap; gap:10px;">
                                @if($item['date_label'])
                                    <span style="display:inline-flex; padding:6px 10px; border-radius:999px; background:#f3f4f6; color:#374151; font-size:14px; max-width:100%; overflow-wrap:normal;">
                                        Onderhoudsdatum: {{ $item['date_label'] }}
                                    </span>
                                @endif
                                @if($item['km_label'])
                                    <span style="display:inline-flex; padding:6px 10px; border-radius:999px; background:#f3f4f6; color:#374151; font-size:14px; max-width:100%; overflow-wrap:normal;">
                                        Kilometerstand: {{ $item['km_label'] }}
                                    </span>
                                @endif
                                @if($item['cost_label'])
                                    <span style="display:inline-flex; padding:6px 10px; border-radius:999px; background:#fef3c7; color:#92400e; font-size:14px; max-width:100%; overflow-wrap:normal;">
                                        Kosten: {{ $item['cost_label'] }}
                                    </span>
                                @endif
                            </div>

                            <h3 style="margin:0; font-size:21px; line-height:1.25; color:#111827; overflow-wrap:normal;">{{ $item['description'] !== '' ? $item['description'] : 'Onderhoudsmoment' }}</h3>

                            @if($item['evidence_labels'] !== [])
                                <div style="display:flex; flex-wrap:wrap; gap:8px;">
                                    @foreach($item['evidence_labels'] as $label)
                                        <span style="display:inline-flex; padding:7px 10px; border-radius:999px; background:#eef6ff; color:#1d4ed8; font-size:13px; font-weight:600; max-width:100%; overflow-wrap:normal;">
                                            {{ $label }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                            @if($item['notes'])
                                <p style="margin:0; color:#374151; white-space:pre-line; line-height:1.7; overflow-wrap:normal;">{{ $item['notes'] }}</p>
                            @endif

                            @if($item['public_image_attachments'] !== [])
                                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:12px;">
                                    @foreach($item['public_image_attachments'] as $attachmentIndex => $attachment)
                                        <button
                                            type="button"
                                            data-gallery-open
                                            data-gallery-group="timeline-{{ $loop->parent->index }}"
                                            data-gallery-index="{{ $attachmentIndex }}"
                                            data-gallery-url="{{ $attachment['url'] }}"
                                            data-gallery-alt="{{ $attachment['alt'] }}"
                                            style="padding:0; border:none; background:transparent; cursor:pointer;"
                                        >
                                            <img
                                                src="{{ $attachment['thumbnail_url'] }}"
                                                alt="{{ $attachment['alt'] }}"
                                                style="width:100%; height:160px; object-fit:cover; border-radius:18px; background:#f3f4f6;"
                                            >
                                        </button>
                                    @endforeach
                                </div>
                            @endif

                            @if($item['public_other_attachments'] !== [])
                                <div style="display:grid; gap:10px; margin-top:12px;">
                                    @foreach($item['public_other_attachments'] as $attachment)
                                        <a
                                            href="{{ $attachment['url'] }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            style="display:flex; align-items:center; gap:12px; padding:12px 14px; border-radius:16px; border:1px solid #e5e7eb; background:#fafafa; color:#111827; text-decoration:none;"
                                        >
                                            <span style="display:inline-flex; align-items:center; justify-content:center; min-width:72px; padding:6px 10px; border-radius:999px; background:#eef2ff; color:#3730a3; font-size:12px; font-weight:700; text-transform:uppercase;">
                                                {{ $attachment['kind'] ?? 'bestand' }}
                                            </span>
                                            <span style="font-weight:600; line-height:1.4; overflow-wrap:normal;">
                                                {{ $attachment['label'] ?? basename($attachment['url']) }}
                                            </span>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </article>
                @empty
                    <article style="padding:24px; border-radius:24px; border:1px dashed #d1d5db; background:#f9fafb; color:#374151;">
                        <h3 style="margin:0 0 8px; font-size:22px; color:#111827;">Nog geen publiek onderhoud gedeeld</h3>
                        <p style="margin:0 0 12px; line-height:1.7;">
                            Zodra de eigenaar onderhoud vastlegt, groeit deze pagina uit tot een aantoonbare en deelbare voertuiggeschiedenis.
                        </p>
                        <p style="margin:0; line-height:1.7; color:#4b5563;">
                            Onderhoud met datum, kilometerstand en bewijs helpt later bij verkoop, verificatie en toekomstige overdracht van historie.
                        </p>
                    </article>
                @endforelse
            </div>
        </section>

        <section style="padding:24px; border-radius:28px; background:linear-gradient(135deg, #111827 0%, #1f2937 100%); color:#fff; margin-bottom:32px;">
            <h2 style="margin:0 0 12px; font-size:clamp(1.45rem, 7vw, 1.75rem); overflow-wrap:normal;">Bouw aan een overdraagbare voertuiggeschiedenis</h2>
            <p style="margin:0 0 14px; color:#e5e7eb; max-width:760px; line-height:1.7;">
                Leg onderhoud, kilometerstanden, foto's en belangrijke momenten vast op één centrale plek. Zo groeit je voertuiggeschiedenis rustig mee door de jaren heen en heb je later meer houvast bij verkoop, verificatie of een toekomstige overdracht.
            </p>
            <a
                href="https://app.garagebook.nl/start"
                style="display:inline-flex; align-items:center; justify-content:center; padding:12px 18px; border-radius:999px; background:#ffd200; color:#111827; text-decoration:none; font-weight:700; max-width:100%; text-align:center;"
            >
                Start met je eigen historie
            </a>
        </section>

        <section style="display:grid; gap:12px;">
            <h2 style="margin:0; font-size:clamp(1.35rem, 7vw, 1.5rem); color:#111827; overflow-wrap:normal;">Ook je onderhoud digitaal bijhouden?</h2>
            <a href="https://garagebook.nl/digitaal-onderhoudsboekje/" style="color:#111827; font-weight:700;">
                Lees meer over het digitale onderhoudsboekje van GarageBook
            </a>
            @if($typeSpecificLandingUrl)
                <a href="{{ $typeSpecificLandingUrl }}" style="color:#111827; font-weight:700;">
                    Bekijk de onderhoudspagina die past bij dit voertuig
                </a>
            @endif
            <a href="https://app.garagebook.nl/start" style="color:#111827; font-weight:700;">
                Bouw je eigen deelbare voertuiggeschiedenis
            </a>
        </section>
    </article>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const triggers = Array.from(document.querySelectorAll('[data-gallery-open]'));
        if (!triggers.length) return;

        const groups = new Map();
        triggers.forEach((trigger) => {
            const group = trigger.dataset.galleryGroup;
            if (!groups.has(group)) groups.set(group, []);
            groups.get(group).push({
                url: trigger.dataset.galleryUrl,
                alt: trigger.dataset.galleryAlt || '',
            });
        });

        let currentGroup = null;
        let currentIndex = 0;

        const overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(17,24,39,.84);display:none;align-items:center;justify-content:center;z-index:9999;padding:16px;';
        overlay.innerHTML = '<button type="button" aria-label="Sluiten" data-lightbox-close style="position:absolute;top:16px;right:16px;width:42px;height:42px;border:none;border-radius:999px;background:#fff;cursor:pointer;font-size:20px;">×</button><button type="button" aria-label="Vorige foto" data-lightbox-prev style="position:absolute;left:16px;top:50%;transform:translateY(-50%);width:42px;height:42px;border:none;border-radius:999px;background:#fff;cursor:pointer;font-size:24px;">‹</button><img data-lightbox-image src="" alt="" style="max-width:min(1100px,100%);max-height:88vh;border-radius:14px;object-fit:contain;background:#0f172a;"><button type="button" aria-label="Volgende foto" data-lightbox-next style="position:absolute;right:16px;top:50%;transform:translateY(-50%);width:42px;height:42px;border:none;border-radius:999px;background:#fff;cursor:pointer;font-size:24px;">›</button>';
        document.body.appendChild(overlay);

        const image = overlay.querySelector('[data-lightbox-image]');
        const prev = overlay.querySelector('[data-lightbox-prev]');
        const next = overlay.querySelector('[data-lightbox-next]');

        const render = () => {
            const images = groups.get(currentGroup) || [];
            if (!images.length) return;
            const item = images[currentIndex];
            image.src = item.url;
            image.alt = item.alt;
            const hasMultiple = images.length > 1;
            prev.style.display = hasMultiple ? 'block' : 'none';
            next.style.display = hasMultiple ? 'block' : 'none';
        };

        const open = (group, index) => {
            currentGroup = group;
            currentIndex = index;
            render();
            overlay.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        };

        const close = () => {
            overlay.style.display = 'none';
            document.body.style.overflow = '';
        };

        const step = (delta) => {
            const images = groups.get(currentGroup) || [];
            if (images.length < 2) return;
            currentIndex = (currentIndex + delta + images.length) % images.length;
            render();
        };

        triggers.forEach((trigger) => {
            trigger.addEventListener('click', function () {
                open(trigger.dataset.galleryGroup, Number(trigger.dataset.galleryIndex || 0));
            });
        });

        overlay.querySelector('[data-lightbox-close]').addEventListener('click', close);
        prev.addEventListener('click', () => step(-1));
        next.addEventListener('click', () => step(1));
        overlay.addEventListener('click', (event) => {
            if (event.target === overlay) close();
        });
        document.addEventListener('keydown', (event) => {
            if (overlay.style.display !== 'flex') return;
            if (event.key === 'Escape') close();
            if (event.key === 'ArrowLeft') step(-1);
            if (event.key === 'ArrowRight') step(1);
        });
    });
</script>
@endpush
