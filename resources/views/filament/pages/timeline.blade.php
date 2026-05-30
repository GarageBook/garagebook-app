<x-filament-panels::page>
    <style>
        .gb-timeline-page {
            --gb-timeline-ink: #0f172a;
            --gb-timeline-muted: #5b6475;
            --gb-timeline-panel: rgba(255, 255, 255, 0.84);
            --gb-timeline-accent: #ffd200;
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
            min-width: 0;
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
            overflow-wrap: anywhere;
        }

        .gb-timeline-hero__subtitle {
            max-width: 44rem;
            color: rgba(255, 255, 255, 0.74);
            font-size: 0.98rem;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .gb-timeline-selector {
            min-width: min(100%, 21rem);
            display: grid;
            gap: 0.55rem;
            max-width: 100%;
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
            min-width: 0;
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
            overflow-wrap: anywhere;
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

        .gb-timeline-board__legend {
            position: relative;
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 0.9rem;
            padding: 0 0.3rem;
            min-width: 0;
        }

        .gb-timeline-board__legend-label {
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: #64748b;
            overflow-wrap: anywhere;
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
            background: linear-gradient(90deg, rgba(255, 210, 0, 0.26), rgba(255, 210, 0, 1) 10%, rgba(255, 210, 0, 1) 90%, rgba(255, 210, 0, 0.26));
            box-shadow: 0 0 0 1px rgba(255, 210, 0, 0.12);
        }

        .gb-timeline-entry {
            position: relative;
            scroll-snap-align: start;
            display: grid;
            grid-template-rows: minmax(15rem, auto) 2.6rem minmax(13rem, auto);
            gap: 0;
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
            z-index: 3;
        }

        .gb-timeline-entry__top,
        .gb-timeline-entry__bottom {
            position: relative;
            display: grid;
            gap: 1rem;
            padding-left: 2.8rem;
        }

        .gb-timeline-entry__top {
            align-content: end;
            padding-bottom: 2.2rem;
        }

        .gb-timeline-entry__bottom {
            align-content: start;
            padding-top: 2.2rem;
        }

        .gb-timeline-entry__top::after,
        .gb-timeline-entry__bottom::before {
            content: "";
            position: absolute;
            left: 1.7rem;
            width: 2px;
            background: linear-gradient(180deg, rgba(255, 210, 0, 0.0), rgba(15, 23, 42, 0.14), rgba(15, 23, 42, 0.02));
        }

        .gb-timeline-entry__top::after {
            top: 3.2rem;
            bottom: 0.4rem;
        }

        .gb-timeline-entry__bottom::before {
            top: 0.4rem;
            bottom: 3.2rem;
        }

        .gb-timeline-entry__anchor-wrap {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: flex-start;
        }

        .gb-timeline-entry__anchor {
            position: relative;
            left: 1.15rem;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 1.1rem;
            height: 1.1rem;
            border-radius: 999px;
            background: #111827;
            box-shadow: 0 0 0 6px rgba(255, 210, 0, 0.38), 0 10px 26px rgba(15, 23, 42, 0.20);
        }

        .gb-timeline-entry__anchor::after {
            content: "";
            width: 0.45rem;
            height: 0.45rem;
            border-radius: 999px;
            background: var(--gb-timeline-accent);
        }

        .gb-timeline-card {
            overflow: hidden;
            border-radius: 26px;
            border: 1px solid rgba(226, 232, 240, 0.95);
            background: var(--gb-timeline-panel);
            backdrop-filter: blur(12px);
            box-shadow: 0 18px 35px rgba(15, 23, 42, 0.08);
            min-width: 0;
        }

        .gb-timeline-card__media {
            aspect-ratio: 16 / 9;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.12), rgba(255, 210, 0, 0.22));
            overflow: hidden;
        }

        .gb-timeline-card__media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .gb-timeline-card__media-empty {
            display: grid;
            place-items: center;
            text-align: center;
            gap: 0.45rem;
            height: 100%;
            padding: 1.5rem;
            background: radial-gradient(circle at top left, rgba(255,255,255,0.52), transparent 50%), linear-gradient(145deg, rgba(15, 23, 42, 0.10), rgba(255, 210, 0, 0.18));
            color: #0f172a;
        }

        .gb-timeline-card__media-empty strong {
            display: block;
            font-size: 1.05rem;
            font-weight: 700;
        }

        .gb-timeline-card__media-empty span {
            color: #475569;
            font-size: 0.92rem;
        }

        .gb-timeline-card__body {
            display: grid;
            gap: 0.9rem;
            padding: 1.15rem;
        }

        .gb-timeline-card__meta {
            display: flex;
            align-items: start;
            justify-content: space-between;
            gap: 0.75rem;
            min-width: 0;
        }

        .gb-timeline-card__date {
            display: grid;
            gap: 0.1rem;
            color: var(--gb-timeline-ink);
        }

        .gb-timeline-card__day {
            font-size: 1.8rem;
            line-height: 1;
            font-weight: 700;
        }

        .gb-timeline-card__month {
            font-size: 0.84rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--gb-timeline-muted);
        }

        .gb-timeline-card__cost {
            color: var(--gb-timeline-ink);
            font-weight: 700;
        }

        .gb-timeline-card__title {
            font-size: 1.15rem;
            line-height: 1.3;
            color: var(--gb-timeline-ink);
            overflow-wrap: anywhere;
        }

        .gb-timeline-card__summary {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .gb-timeline-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.42rem 0.72rem;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.06);
            color: var(--gb-timeline-muted);
            font-size: 0.82rem;
            font-weight: 600;
            max-width: 100%;
            overflow-wrap: anywhere;
            white-space: normal;
        }

        .gb-timeline-card__notes {
            color: var(--gb-timeline-muted);
            line-height: 1.7;
            overflow-wrap: anywhere;
        }

        .gb-timeline-card__button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: fit-content;
            padding: 0.72rem 0.95rem;
            border: none;
            border-radius: 14px;
            background: #111827;
            color: white;
            font-weight: 700;
            cursor: pointer;
            max-width: 100%;
            white-space: normal;
        }

        .gb-timeline-empty {
            padding: 1.6rem;
            border-radius: 30px;
            border: 1px dashed rgba(148, 163, 184, 0.38);
            background: rgba(255, 255, 255, 0.72);
            color: var(--gb-timeline-muted);
            box-shadow: var(--gb-timeline-shadow);
        }

        .gb-trips-card {
            position: relative;
            display: grid;
            gap: 1rem;
            padding: 1.1rem;
            border-radius: 24px;
            border: 1px solid rgba(147, 197, 253, 0.34);
            background: linear-gradient(180deg, rgba(239, 246, 255, 0.92), rgba(255, 255, 255, 0.92));
            box-shadow: 0 14px 32px rgba(15, 23, 42, 0.08);
        }

        .gb-trips-card::before {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: 24px;
            background: radial-gradient(circle at top right, rgba(59, 130, 246, 0.10), transparent 40%);
            pointer-events: none;
        }

        .gb-trips-card__label {
            width: fit-content;
            padding: 0.35rem 0.6rem;
            border-radius: 999px;
            background: rgba(37, 99, 235, 0.12);
            color: #1d4ed8;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .gb-trips-card__title {
            font-size: 1.1rem;
            line-height: 1.3;
            font-weight: 700;
            color: #0f172a;
            overflow-wrap: anywhere;
        }

        .gb-trips-card__meta {
            display: grid;
            gap: 0.45rem;
            color: #475569;
            font-size: 0.92rem;
        }

        .gb-trips-card__thumbs {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.55rem;
        }

        .gb-trips-card__thumb {
            display: block;
            aspect-ratio: 1 / 1;
            overflow: hidden;
            border-radius: 16px;
            border: 1px solid rgba(191, 219, 254, 0.85);
            background: #dbeafe;
        }

        .gb-trips-card__thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .gb-trips-card__thumb-empty {
            display: grid;
            place-items: center;
            aspect-ratio: 1 / 1;
            border-radius: 16px;
            border: 1px dashed rgba(147, 197, 253, 0.9);
            background: rgba(219, 234, 254, 0.56);
            color: #64748b;
            font-size: 0.85rem;
            text-align: center;
            padding: 0.75rem;
        }

        .gb-trips-card__cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: fit-content;
            padding: 0.78rem 0.95rem;
            border-radius: 14px;
            background: #0f172a;
            color: white;
            font-weight: 700;
            text-decoration: none;
            max-width: 100%;
            white-space: normal;
            overflow-wrap: anywhere;
        }

        .gb-timeline-modal {
            position: fixed;
            inset: 0;
            z-index: 60;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
            background: rgba(15, 23, 42, 0.74);
            backdrop-filter: blur(8px);
        }

        .gb-timeline-modal[x-cloak] {
            display: none !important;
        }

        .gb-timeline-modal__dialog {
            width: min(100%, 70rem);
            max-height: calc(100vh - 2rem);
            overflow: auto;
            border-radius: 28px;
            background: white;
            box-shadow: 0 28px 80px rgba(15, 23, 42, 0.28);
            min-width: 0;
        }

        .gb-timeline-modal__grid {
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(20rem, 0.85fr);
        }

        .gb-timeline-modal__gallery {
            position: relative;
            min-height: 22rem;
            background: #0f172a;
        }

        .gb-timeline-modal__gallery-frame {
            height: 100%;
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
            width: 2.9rem;
            height: 2.9rem;
            border: none;
            border-radius: 999px;
            background: rgba(255,255,255,0.84);
            color: #111827;
            font-size: 1.4rem;
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
            padding: 0.42rem 0.7rem;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.68);
            color: white;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .gb-timeline-modal__content {
            display: grid;
            gap: 1rem;
            padding: 1.5rem;
            min-width: 0;
        }

        .gb-timeline-modal__eyebrow {
            font-size: 0.78rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #64748b;
        }

        .gb-timeline-modal__title {
            font-size: 1.55rem;
            line-height: 1.05;
            color: #0f172a;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .gb-timeline-modal__text {
            color: #475569;
            line-height: 1.7;
            overflow-wrap: anywhere;
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
            min-width: 0;
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
            flex-shrink: 0;
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
                min-width: 0;
            }

            .gb-timeline-track {
                grid-auto-columns: minmax(15.5rem, 18rem);
                padding-top: 2.4rem;
                padding-bottom: 2.6rem;
            }

            .gb-timeline-modal {
                padding: 0.75rem;
            }

            .gb-timeline-modal__gallery {
                min-height: 16rem;
            }

            .gb-timeline-modal__content {
                padding: 1rem;
            }

            .gb-timeline-file {
                align-items: flex-start;
                flex-direction: column;
                gap: 0.55rem;
            }

            .gb-timeline-file__link {
                white-space: normal;
            }

            .gb-timeline-entry {
                grid-template-rows: minmax(14rem, auto) 2.4rem minmax(12rem, auto);
            }

            .gb-timeline-entry__top,
            .gb-timeline-entry__bottom {
                padding-left: 2.2rem;
            }

            .gb-timeline-entry__top::after,
            .gb-timeline-entry__bottom::before {
                left: 1.3rem;
            }

            .gb-timeline-entry__anchor {
                left: 0.8rem;
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
                        <div class="gb-timeline-summary__value">{{ $totalItems }}</div>
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

            @if($activeVehicle && count($timelineGroups))
                <section class="gb-timeline-board">
                    <div class="gb-timeline-board__legend">
                        <div class="gb-timeline-board__legend-label">{{ __('dashboard.timeline.maintenance_section') }}</div>
                        <div class="gb-timeline-board__legend-label">{{ __('dashboard.timeline.trips_section') }}</div>
                    </div>

                    <div class="gb-timeline-scroll">
                        <div class="gb-timeline-track">
                            @foreach($timelineGroups as $group)
                                <article class="gb-timeline-entry">
                                    @if($group['showYearMarker'])
                                        <div class="gb-timeline-entry__year">{{ $group['year'] }}</div>
                                    @endif

                                    <div class="gb-timeline-entry__top">
                                        @foreach($group['maintenanceItems'] as $entry)
                                            <div class="gb-timeline-card">
                                                <div class="gb-timeline-card__media">
                                                    @if($entry['previewImage'])
                                                        <img src="{{ $entry['previewImage'] }}" alt="{{ $entry['title'] }}" loading="lazy" decoding="async">
                                                    @else
                                                        <div class="gb-timeline-card__media-empty">
                                                            <strong>{{ $entry['fallbackBrand'] }}</strong>
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
                                        @endforeach
                                    </div>

                                    <div class="gb-timeline-entry__anchor-wrap">
                                        <div class="gb-timeline-entry__anchor"></div>
                                    </div>

                                    <div class="gb-timeline-entry__bottom">
                                        @foreach($group['tripItems'] as $trip)
                                            <article class="gb-trips-card">
                                                <div class="gb-trips-card__label">{{ $trip['label'] }}</div>

                                                <div class="gb-trips-card__title">{{ $trip['title'] }}</div>

                                                <div class="gb-trips-card__meta">
                                                    @if($trip['riddenLabel'])
                                                        <div>{{ $trip['riddenLabel'] }}</div>
                                                    @endif
                                                    @if($trip['metaLabel'])
                                                        <div>{{ $trip['metaLabel'] }}</div>
                                                    @endif
                                                </div>

                                                <div class="gb-timeline-card__summary">
                                                    @if($trip['distanceLabel'])
                                                        <span class="gb-timeline-pill">{{ $trip['distanceLabel'] }}</span>
                                                    @endif
                                                    @if($trip['photoCountLabel'])
                                                        <span class="gb-timeline-pill">{{ $trip['photoCountLabel'] }}</span>
                                                    @endif
                                                </div>

                                                @if(count($trip['previewPhotos']))
                                                    <div class="gb-trips-card__thumbs">
                                                        @foreach($trip['previewPhotos'] as $photo)
                                                            <a class="gb-trips-card__thumb" href="{{ $photo['url'] }}" target="_blank" rel="noopener noreferrer">
                                                                <img src="{{ $photo['url'] }}" alt="{{ $trip['title'] }}" loading="lazy" decoding="async">
                                                            </a>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <div class="gb-trips-card__thumb-empty">{{ __('dashboard.timeline.trips_no_photos') }}</div>
                                                @endif

                                                <a class="gb-trips-card__cta" href="{{ $trip['tripUrl'] }}">{{ __('dashboard.timeline.view_trip') }}</a>
                                            </article>
                                        @endforeach
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </div>
                </section>
            @elseif($activeVehicle)
                <section class="gb-timeline-empty">
                    {{ __('dashboard.timeline.empty_with_vehicle') }}
                </section>
            @else
                <section class="gb-timeline-empty">
                    {{ __('dashboard.timeline.empty_without_vehicle') }}
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
