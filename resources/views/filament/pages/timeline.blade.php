<x-filament-panels::page>
    <style>
        .gb-timeline-page {
            --gb-timeline-ink: #0f172a;
            --gb-timeline-muted: #5b6475;
            --gb-timeline-line: rgba(15, 23, 42, 0.16);
            --gb-timeline-panel: rgba(255, 255, 255, 0.84);
            --gb-timeline-accent: #ffd200;
            --gb-timeline-accent-deep: #f5b700;
            --gb-timeline-shadow: 0 24px 60px rgba(15, 23, 42, 0.10);
            position: relative;
            display: grid;
            gap: 1.5rem;
        }

        .gb-timeline-page::before {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: 32px;
            background:
                radial-gradient(circle at top left, rgba(255, 210, 0, 0.20), transparent 28rem),
                linear-gradient(180deg, #fffdf6 0%, #fff 42%, #f8fafc 100%);
            pointer-events: none;
        }

        .gb-timeline-shell {
            position: relative;
            display: grid;
            gap: 1.5rem;
        }

        .gb-timeline-hero {
            display: grid;
            gap: 1rem;
            padding: 1.5rem;
            border-radius: 28px;
            background: linear-gradient(145deg, rgba(15, 23, 42, 0.94), rgba(30, 41, 59, 0.92));
            color: white;
            box-shadow: var(--gb-timeline-shadow);
        }

        .gb-timeline-hero__top {
            display: flex;
            flex-wrap: wrap;
            align-items: end;
            justify-content: space-between;
            gap: 1rem;
        }

        .gb-timeline-hero__eyebrow {
            font-size: 0.78rem;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.66);
        }

        .gb-timeline-hero__title {
            margin-top: 0.35rem;
            font-size: clamp(2rem, 4vw, 3rem);
            line-height: 0.96;
            font-weight: 700;
        }

        .gb-timeline-hero__subtitle {
            max-width: 44rem;
            color: rgba(255, 255, 255, 0.74);
            font-size: 0.98rem;
            line-height: 1.6;
        }

        .gb-timeline-selector {
            min-width: min(100%, 21rem);
            display: grid;
            gap: 0.55rem;
        }

        .gb-timeline-selector__label {
            font-size: 0.78rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.68);
        }

        .gb-timeline-selector__field {
            min-height: 3.2rem;
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 16px;
            padding: 0.85rem 3rem 0.85rem 1rem;
            color: #fff;
            font-weight: 600;
            background:
                linear-gradient(180deg, rgba(255,255,255,0.10), rgba(255,255,255,0.04)),
                url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='none'%3E%3Cpath d='M5 7.5 10 12.5 15 7.5' stroke='%23ffffff' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") no-repeat right 1rem center / 1rem;
            appearance: none;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
        }

        .gb-timeline-selector__field option {
            color: #111827;
        }

        .gb-timeline-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.9rem;
        }

        .gb-timeline-summary__card {
            padding: 1rem 1.1rem;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
        }

        .gb-timeline-summary__label {
            color: rgba(255, 255, 255, 0.62);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .gb-timeline-summary__value {
            margin-top: 0.4rem;
            font-size: 1.35rem;
            line-height: 1.1;
            font-weight: 700;
        }

        .gb-timeline-board {
            position: relative;
            padding: 1.4rem;
            border-radius: 30px;
            background: rgba(255, 255, 255, 0.72);
            border: 1px solid rgba(226, 232, 240, 0.9);
            box-shadow: var(--gb-timeline-shadow);
            overflow: hidden;
        }

        .gb-timeline-board::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(to right, rgba(15, 23, 42, 0.04) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(15, 23, 42, 0.04) 1px, transparent 1px);
            background-size: 2rem 2rem;
            mask-image: linear-gradient(180deg, rgba(0,0,0,0.32), transparent 88%);
            pointer-events: none;
        }

        .gb-timeline-scroll {
            position: relative;
            overflow-x: auto;
            overflow-y: visible;
            padding-bottom: 0.5rem;
            overscroll-behavior-x: contain;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            scrollbar-color: rgba(15, 23, 42, 0.22) transparent;
        }

        .gb-timeline-scroll::-webkit-scrollbar {
            height: 10px;
        }

        .gb-timeline-scroll::-webkit-scrollbar-thumb {
            background: rgba(15, 23, 42, 0.20);
            border-radius: 999px;
        }

        .gb-timeline-track {
            position: relative;
            display: grid;
            grid-auto-flow: column;
            grid-auto-columns: minmax(18rem, 21rem);
            gap: 1.25rem;
            min-width: max-content;
            padding: 3rem 0 3.3rem;
        }

        .gb-timeline-track::before {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            top: 50%;
            height: 2px;
            transform: translateY(-50%);
            background:
                linear-gradient(90deg, rgba(255, 210, 0, 0.26), rgba(255, 210, 0, 1) 10%, rgba(255, 210, 0, 1) 90%, rgba(255, 210, 0, 0.26));
            box-shadow: 0 0 0 1px rgba(255, 210, 0, 0.12);
        }

        .gb-timeline-entry {
            position: relative;
            scroll-snap-align: start;
        }

        .gb-timeline-entry__year {
            position: absolute;
            left: 0;
            top: 0;
            padding: 0.38rem 0.8rem;
            border-radius: 999px;
            background: #111827;
            color: white;
            font-size: 0.72rem;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            z-index: 2;
        }

        .gb-timeline-entry__anchor {
            position: absolute;
            left: 1.2rem;
            top: 50%;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 1.1rem;
            height: 1.1rem;
            border-radius: 999px;
            background: #111827;
            box-shadow: 0 0 0 6px rgba(255, 210, 0, 0.38), 0 10px 26px rgba(15, 23, 42, 0.20);
            transform: translateY(-50%);
        }

        .gb-timeline-entry__anchor::after {
            content: "";
            width: 0.45rem;
            height: 0.45rem;
            border-radius: 999px;
            background: var(--gb-timeline-accent);
        }

        .gb-timeline-entry--top .gb-timeline-entry__card-wrap {
            padding-bottom: 11rem;
        }

        .gb-timeline-entry--bottom .gb-timeline-entry__card-wrap {
            padding-top: 11rem;
        }

        .gb-timeline-entry__stem {
            position: absolute;
            left: 1.7rem;
            width: 2px;
            background: linear-gradient(180deg, rgba(255, 210, 0, 0.0), rgba(15, 23, 42, 0.14), rgba(15, 23, 42, 0.02));
        }

        .gb-timeline-entry--top .gb-timeline-entry__stem {
            top: 3.2rem;
            bottom: 50%;
            margin-bottom: 1.2rem;
        }

        .gb-timeline-entry--bottom .gb-timeline-entry__stem {
            top: 50%;
            bottom: 2.8rem;
            margin-top: 1.2rem;
        }

        .gb-timeline-card {
            position: relative;
            margin-left: 2.8rem;
            border: 1px solid rgba(226, 232, 240, 0.96);
            border-radius: 24px;
            overflow: hidden;
            background: var(--gb-timeline-panel);
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.09);
            backdrop-filter: blur(14px);
        }

        .gb-timeline-card__media {
            position: relative;
            aspect-ratio: 3 / 2;
            overflow: hidden;
            background:
                linear-gradient(135deg, rgba(255, 210, 0, 0.28), rgba(255, 255, 255, 0.28)),
                linear-gradient(145deg, #0f172a, #334155);
        }

        .gb-timeline-card__media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .gb-timeline-card__media-empty {
            display: flex;
            align-items: end;
            justify-content: space-between;
            width: 100%;
            height: 100%;
            padding: 1rem;
            color: white;
            background:
                radial-gradient(circle at top right, rgba(255, 210, 0, 0.55), transparent 14rem),
                linear-gradient(145deg, #0f172a, #334155);
        }

        .gb-timeline-card__media-empty strong {
            font-size: 1.6rem;
            line-height: 1;
            font-weight: 700;
            letter-spacing: -0.04em;
            max-width: 10rem;
        }

        .gb-timeline-card__media-empty span {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: rgba(255, 255, 255, 0.7);
        }

        .gb-timeline-card__body {
            display: grid;
            gap: 0.9rem;
            padding: 1.05rem 1.05rem 1.15rem;
        }

        .gb-timeline-card__meta {
            display: flex;
            justify-content: space-between;
            gap: 0.8rem;
            align-items: start;
        }

        .gb-timeline-card__date {
            display: inline-flex;
            gap: 0.55rem;
            align-items: baseline;
            color: var(--gb-timeline-ink);
        }

        .gb-timeline-card__day {
            font-size: 1.55rem;
            line-height: 1;
            font-weight: 700;
            letter-spacing: -0.06em;
        }

        .gb-timeline-card__month {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--gb-timeline-muted);
        }

        .gb-timeline-card__cost {
            padding: 0.45rem 0.7rem;
            border-radius: 999px;
            background: rgba(255, 210, 0, 0.22);
            color: var(--gb-timeline-ink);
            font-size: 0.8rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .gb-timeline-card__title {
            font-size: 1.05rem;
            line-height: 1.25;
            font-weight: 700;
            color: var(--gb-timeline-ink);
        }

        .gb-timeline-card__summary {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .gb-timeline-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.42rem 0.7rem;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.06);
            color: var(--gb-timeline-muted);
            font-size: 0.78rem;
            font-weight: 600;
        }

        .gb-timeline-card__notes {
            color: var(--gb-timeline-muted);
            font-size: 0.9rem;
            line-height: 1.55;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 3;
            overflow: hidden;
        }

        .gb-timeline-card__button {
            width: fit-content;
            margin-top: 0.2rem;
            padding: 0.58rem 0.9rem;
            border: 1px solid rgba(15, 23, 42, 0.12);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.7);
            color: #0f172a;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
        }

        .gb-timeline-empty {
            position: relative;
            padding: 3rem 2rem;
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.72);
            border: 1px solid rgba(226, 232, 240, 0.9);
            color: var(--gb-timeline-muted);
            text-align: center;
            box-shadow: var(--gb-timeline-shadow);
        }

        .gb-timeline-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: rgba(15, 23, 42, 0.62);
            backdrop-filter: blur(10px);
            z-index: 60;
        }

        .gb-timeline-modal[x-cloak] {
            display: none !important;
        }

        .gb-timeline-modal__dialog {
            width: min(100%, 72rem);
            max-height: calc(100vh - 3rem);
            overflow: auto;
            border-radius: 28px;
            background: #fff;
            box-shadow: 0 36px 90px rgba(15, 23, 42, 0.26);
        }

        .gb-timeline-modal__grid {
            display: grid;
            grid-template-columns: minmax(0, 1.25fr) minmax(20rem, 0.85fr);
        }

        .gb-timeline-modal__gallery {
            position: relative;
            min-height: 100%;
            background: linear-gradient(145deg, #0f172a, #1e293b);
        }

        .gb-timeline-modal__gallery-frame {
            aspect-ratio: 4 / 3;
            min-height: 100%;
        }

        .gb-timeline-modal__gallery-frame img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .gb-timeline-modal__nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 2.8rem;
            height: 2.8rem;
            border: none;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.56);
            color: white;
            font-size: 1.8rem;
            cursor: pointer;
        }

        .gb-timeline-modal__nav--prev {
            left: 1rem;
        }

        .gb-timeline-modal__nav--next {
            right: 1rem;
        }

        .gb-timeline-modal__count {
            position: absolute;
            left: 1rem;
            bottom: 1rem;
            padding: 0.45rem 0.8rem;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.56);
            color: white;
            font-size: 0.78rem;
        }

        .gb-timeline-modal__content {
            display: grid;
            gap: 1rem;
            padding: 1.4rem;
        }

        .gb-timeline-modal__eyebrow {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #64748b;
        }

        .gb-timeline-modal__title {
            font-size: 1.55rem;
            line-height: 1.05;
            color: #0f172a;
            font-weight: 700;
        }

        .gb-timeline-modal__text {
            color: #475569;
            line-height: 1.7;
        }

        .gb-timeline-modal__files {
            display: grid;
            gap: 0.7rem;
        }

        .gb-timeline-file {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: center;
            padding: 0.85rem 0.95rem;
            border: 1px solid rgba(226, 232, 240, 0.95);
            border-radius: 16px;
            background: #f8fafc;
        }

        .gb-timeline-file__meta {
            min-width: 0;
        }

        .gb-timeline-file__type {
            font-size: 0.74rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #64748b;
        }

        .gb-timeline-file__label {
            margin-top: 0.15rem;
            color: #0f172a;
            font-size: 0.9rem;
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        .gb-timeline-file__link {
            color: #0f172a;
            font-size: 0.85rem;
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
        }

        .gb-timeline-modal__close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 2.8rem;
            height: 2.8rem;
            border: none;
            border-radius: 999px;
            background: rgba(255,255,255,0.84);
            color: #0f172a;
            font-size: 1.4rem;
            cursor: pointer;
            z-index: 4;
        }

        @media (max-width: 1024px) {
            .gb-timeline-summary {
                grid-template-columns: 1fr;
            }

            .gb-timeline-modal__grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .gb-timeline-page {
                gap: 1rem;
            }

            .gb-timeline-hero,
            .gb-timeline-board,
            .gb-timeline-empty {
                border-radius: 24px;
                padding: 1rem;
            }

            .gb-timeline-track {
                grid-auto-columns: minmax(16rem, 18rem);
                padding-top: 2.4rem;
                padding-bottom: 2.6rem;
            }

            .gb-timeline-entry--top .gb-timeline-entry__card-wrap,
            .gb-timeline-entry--bottom .gb-timeline-entry__card-wrap {
                padding-top: 9rem;
                padding-bottom: 0;
            }

            .gb-timeline-entry__stem {
                top: 50% !important;
                bottom: 2.6rem !important;
                margin-top: 1rem !important;
                margin-bottom: 0 !important;
            }

            .gb-timeline-card {
                margin-left: 2.2rem;
            }
        }
    </style>

    <div
        class="gb-timeline-page"
        x-data="{
            entries: @js($entries),
            selectedEntry: null,
            selectedImageIndex: 0,
            openEntry(entryId) {
                this.selectedEntry = this.entries.find((entry) => entry.id === entryId) ?? null;
                this.selectedImageIndex = 0;
                this.syncBodyScroll();
            },
            closeEntry() {
                this.selectedEntry = null;
                this.selectedImageIndex = 0;
                this.syncBodyScroll();
            },
            nextImage() {
                if (! this.selectedEntry || this.selectedEntry.images.length < 2) {
                    return;
                }

                this.selectedImageIndex = (this.selectedImageIndex + 1) % this.selectedEntry.images.length;
            },
            prevImage() {
                if (! this.selectedEntry || this.selectedEntry.images.length < 2) {
                    return;
                }

                this.selectedImageIndex = (this.selectedImageIndex - 1 + this.selectedEntry.images.length) % this.selectedEntry.images.length;
            },
            syncBodyScroll() {
                document.body.style.overflow = this.selectedEntry ? 'hidden' : '';
            }
        }"
        @keydown.window.prevent.escape="selectedEntry && closeEntry()"
        @keydown.window.prevent.arrow-right="selectedEntry && nextImage()"
        @keydown.window.prevent.arrow-left="selectedEntry && prevImage()"
    >
        <div class="gb-timeline-shell">
            <section class="gb-timeline-hero">
                <div class="gb-timeline-hero__top">
                    <div>
                        <div class="gb-timeline-hero__eyebrow">{{ __('dashboard.timeline.hero_eyebrow') }}</div>
                        <div class="gb-timeline-hero__title">{{ $activeVehicle?->nickname ?: ($activeVehicle ? $activeVehicle->brand . ' ' . $activeVehicle->model : __('dashboard.timeline.no_vehicle')) }}</div>
                        <div class="gb-timeline-hero__subtitle">
                            {{ __('dashboard.timeline.hero_subtitle') }}
                        </div>
                    </div>

                    <div class="gb-timeline-selector">
                        <label class="gb-timeline-selector__label" for="timelineVehicle">{{ __('dashboard.timeline.active_vehicle') }}</label>
                        <select id="timelineVehicle" class="gb-timeline-selector__field" wire:model.live="activeVehicleId">
                            @forelse($vehicles as $vehicle)
                                <option value="{{ $vehicle->id }}">
                                    {{ $vehicle->nickname ?: ($vehicle->brand . ' ' . $vehicle->model) }}
                                </option>
                            @empty
                                <option value="">{{ __('dashboard.timeline.no_vehicles_available') }}</option>
                            @endforelse
                        </select>
                    </div>
                </div>

                <div class="gb-timeline-summary">
                    <div class="gb-timeline-summary__card">
                        <div class="gb-timeline-summary__label">{{ __('dashboard.timeline.summary_items') }}</div>
                        <div class="gb-timeline-summary__value">{{ count($entries) }}</div>
                    </div>

                    <div class="gb-timeline-summary__card">
                        <div class="gb-timeline-summary__label">{{ __('dashboard.timeline.summary_period') }}</div>
                        <div class="gb-timeline-summary__value">{{ $periodLabel ?: __('dashboard.timeline.period_empty') }}</div>
                    </div>

                    <div class="gb-timeline-summary__card">
                        <div class="gb-timeline-summary__label">{{ __('dashboard.timeline.summary_total_cost') }}</div>
                        <div class="gb-timeline-summary__value">{{ $totalCostLabel }}</div>
                    </div>
                </div>
            </section>

            @if($activeVehicle && count($entries))
                <section class="gb-timeline-board">
                    <div class="gb-timeline-scroll">
                        <div class="gb-timeline-track">
                            @foreach($entries as $entry)
                                <article class="gb-timeline-entry gb-timeline-entry--{{ $entry['side'] }}">
                                    @if($entry['showYearMarker'])
                                        <div class="gb-timeline-entry__year">{{ $entry['year'] }}</div>
                                    @endif

                                    <div class="gb-timeline-entry__anchor"></div>
                                    <div class="gb-timeline-entry__stem"></div>

                                    <div class="gb-timeline-entry__card-wrap">
                                        <div class="gb-timeline-card">
                                            <div class="gb-timeline-card__media">
                                                @if($entry['previewImage'])
                                                    <img src="{{ $entry['previewImage'] }}" alt="{{ $entry['title'] }}" loading="lazy" decoding="async">
                                                @else
                                                    <div class="gb-timeline-card__media-empty">
                                                        <strong>{{ $activeVehicle->brand }}</strong>
                                                        <span>{{ $entry['dateLabel'] }}</span>
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="gb-timeline-card__body">
                                                <div class="gb-timeline-card__meta">
                                                    <div class="gb-timeline-card__date">
                                                        <span class="gb-timeline-card__day">{{ $entry['dayLabel'] }}</span>
                                                        <span class="gb-timeline-card__month">{{ $entry['monthLabel'] }}</span>
                                                    </div>

                                                    @if($entry['costLabel'])
                                                        <div class="gb-timeline-card__cost">{{ $entry['costLabel'] }}</div>
                                                    @endif
                                                </div>

                                                <div class="gb-timeline-card__title">{{ $entry['title'] }}</div>

                                                <div class="gb-timeline-card__summary">
                                                    <span class="gb-timeline-pill">{{ $entry['distanceLabel'] }}</span>
                                                    @if($entry['workedHoursLabel'])
                                                        <span class="gb-timeline-pill">{{ $entry['workedHoursLabel'] }}</span>
                                                    @endif
                                                    @if($entry['imageCount'])
                                                        <span class="gb-timeline-pill">{{ trans_choice('dashboard.timeline.images_count', $entry['imageCount'], ['count' => $entry['imageCount']]) }}</span>
                                                    @endif
                                                    @if($entry['fileCount'])
                                                        <span class="gb-timeline-pill">{{ trans_choice('dashboard.timeline.files_count', $entry['fileCount'], ['count' => $entry['fileCount']]) }}</span>
                                                    @endif
                                                </div>

                                                @if($entry['notes'])
                                                    <div class="gb-timeline-card__notes">
                                                        {{ $entry['notes'] }}
                                                    </div>
                                                @endif

                                                <button type="button" class="gb-timeline-card__button" @click="openEntry({{ $entry['id'] }})">{{ __('dashboard.timeline.view_moment') }}</button>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </div>
                </section>
            @else
                <section class="gb-timeline-empty">
                    @if($activeVehicle)
                        {{ __('dashboard.timeline.empty_with_vehicle') }}
                    @else
                        {{ __('dashboard.timeline.empty_without_vehicle') }}
                    @endif
                </section>
            @endif
        </div>

        <div
            class="gb-timeline-modal"
            x-cloak
            x-bind:style="selectedEntry ? 'display:flex;' : 'display:none;'"
            @click.self="closeEntry()"
        >
            <div class="gb-timeline-modal__dialog">
                <div class="gb-timeline-modal__grid" x-show="selectedEntry">
                    <div class="gb-timeline-modal__gallery">
                        <button type="button" class="gb-timeline-modal__close" @click="closeEntry()">✕</button>

                        <template x-if="selectedEntry && selectedEntry.images.length">
                            <div class="gb-timeline-modal__gallery-frame">
                                <img :src="selectedEntry.images[selectedImageIndex].full" :alt="selectedEntry.title">
                            </div>
                        </template>

                        <template x-if="selectedEntry && ! selectedEntry.images.length">
                            <div class="gb-timeline-card__media-empty gb-timeline-modal__gallery-frame">
                                <strong>{{ $activeVehicle?->brand }}</strong>
                                <span x-text="selectedEntry?.dateLabel"></span>
                            </div>
                        </template>

                        <button
                            type="button"
                            class="gb-timeline-modal__nav gb-timeline-modal__nav--prev"
                            x-show="selectedEntry && selectedEntry.images.length > 1"
                            @click="prevImage()"
                        >‹</button>

                        <button
                            type="button"
                            class="gb-timeline-modal__nav gb-timeline-modal__nav--next"
                            x-show="selectedEntry && selectedEntry.images.length > 1"
                            @click="nextImage()"
                        >›</button>

                        <div class="gb-timeline-modal__count" x-show="selectedEntry && selectedEntry.images.length > 1" x-text="`${selectedImageIndex + 1} / ${selectedEntry?.images.length}`"></div>
                    </div>

                    <div class="gb-timeline-modal__content">
                        <div class="gb-timeline-modal__eyebrow" x-text="selectedEntry?.dateLabel"></div>
                        <div class="gb-timeline-modal__title" x-text="selectedEntry?.title"></div>

                        <div class="gb-timeline-card__summary">
                            <span class="gb-timeline-pill" x-text="selectedEntry?.distanceLabel"></span>
                            <template x-if="selectedEntry?.costLabel">
                                <span class="gb-timeline-pill" x-text="selectedEntry?.costLabel"></span>
                            </template>
                            <template x-if="selectedEntry?.workedHoursLabel">
                                <span class="gb-timeline-pill" x-text="selectedEntry?.workedHoursLabel"></span>
                            </template>
                        </div>

                        <template x-if="selectedEntry?.notes">
                            <div class="gb-timeline-modal__text" x-text="selectedEntry?.notes"></div>
                        </template>

                        <template x-if="selectedEntry && selectedEntry.files.length">
                            <div class="gb-timeline-modal__files">
                                <template x-for="file in selectedEntry.files" :key="file.url">
                                    <div class="gb-timeline-file">
                                        <div class="gb-timeline-file__meta">
                                            <div class="gb-timeline-file__type" x-text="file.type"></div>
                                            <div class="gb-timeline-file__label" x-text="file.label"></div>
                                        </div>
                                        <a class="gb-timeline-file__link" :href="file.url" target="_blank" rel="noopener noreferrer">{{ __('dashboard.timeline.open_file') }}</a>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
