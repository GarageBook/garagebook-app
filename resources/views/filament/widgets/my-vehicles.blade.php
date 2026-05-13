<x-filament::widget>
    <x-filament::card>
        <h2 style="font-size:20px; font-weight:700; margin-bottom:20px;">
            {{ $translations['heading'] }}
        </h2>

        @forelse($vehicles as $vehicle)
            @php
                $galleryPhotos = $vehicle->dashboard_gallery_photos ?? [asset('images/garagebook-hero-workshop-motor.webp')];
                $hasMultiplePhotos = count($galleryPhotos) > 1;
            @endphp
            <div style="
                border-radius:16px;
                overflow:hidden;
                border:1px solid #e5e7eb;
                background:#fff;
                margin-bottom:16px;
            "
            x-data="{
                photos: @js($galleryPhotos),
                currentIndex: 0,
                lightboxOpen: false,
                hovering: false,
                canHover: window.matchMedia('(hover: hover)').matches,
                next() {
                    this.currentIndex = (this.currentIndex + 1) % this.photos.length;
                },
                prev() {
                    this.currentIndex = (this.currentIndex - 1 + this.photos.length) % this.photos.length;
                },
                openLightbox() {
                    this.lightboxOpen = true;
                    document.body.style.overflow = 'hidden';
                },
                closeLightbox() {
                    this.lightboxOpen = false;
                    document.body.style.overflow = '';
                }
            }"
            @mouseenter="hovering = true"
            @mouseleave="hovering = false"
            @keydown.window.prevent.arrow-right="lightboxOpen && next()"
            @keydown.window.prevent.arrow-left="lightboxOpen && prev()"
            @keydown.window.prevent.escape="lightboxOpen && closeLightbox()"
            >

                <div style="position:relative;">
                    <button
                        type="button"
                        x-show="{{ $hasMultiplePhotos ? 'true' : 'false' }} && (!canHover || hovering)"
                        x-cloak
                        @click="prev()"
                        aria-label="{{ $translations['aria_previous_photo'] }}"
                        style="
                            position:absolute;
                            left:16px;
                            top:50%;
                            transform:translateY(-50%);
                            width:42px;
                            height:42px;
                            border:none;
                            border-radius:999px;
                            background:rgba(17,24,39,0.7);
                            color:#fff;
                            font-size:28px;
                            line-height:1;
                            cursor:pointer;
                            z-index:2;
                        "
                    >‹</button>

                    <button
                        type="button"
                        x-show="{{ $hasMultiplePhotos ? 'true' : 'false' }} && (!canHover || hovering)"
                        x-cloak
                        @click="next()"
                        aria-label="{{ $translations['aria_next_photo'] }}"
                        style="
                            position:absolute;
                            right:16px;
                            top:50%;
                            transform:translateY(-50%);
                            width:42px;
                            height:42px;
                            border:none;
                            border-radius:999px;
                            background:rgba(17,24,39,0.7);
                            color:#fff;
                            font-size:28px;
                            line-height:1;
                            cursor:pointer;
                            z-index:2;
                        "
                    >›</button>

                    <button
                        type="button"
                        @click="openLightbox()"
                        aria-label="{{ $translations['aria_open_gallery'] }}"
                        style="
                            width:100%;
                            padding:0;
                            border:none;
                            background:transparent;
                            cursor:pointer;
                            display:block;
                        "
                    >
                        <img
                            x-bind:src="photos[currentIndex]"
                            alt="{{ $vehicle->brand }} {{ $vehicle->model }}"
                            width="7262"
                            height="2875"
                            loading="lazy"
                            decoding="async"
                            style="
                                width:100%;
                                height:400px;
                                object-fit:cover;
                                display:block;
                            "
                        >
                    </button>

                    <div
                        x-show="{{ $hasMultiplePhotos ? 'true' : 'false' }}"
                        x-cloak
                        style="
                            position:absolute;
                            left:16px;
                            bottom:16px;
                            padding:6px 10px;
                            border-radius:999px;
                            background:rgba(17,24,39,0.7);
                            color:#fff;
                            font-size:12px;
                            font-weight:600;
                            z-index:2;
                        "
                    >
                        <span x-text="`${currentIndex + 1} / ${photos.length}`"></span>
                    </div>
                </div>

                <!-- CONTENT -->
                <div style="padding:18px;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:12px;">
                        <div>
                            <div style="
                                font-weight:700;
                                font-size:16px;
                                margin-bottom:4px;
                            ">
                                {{ $vehicle->nickname ?? ($vehicle->brand . ' ' . $vehicle->model) }}
                            </div>

                            <div style="
                                color:#6b7280;
                                font-size:14px;
                            ">
                                {{ $vehicle->current_distance_label }}
                            </div>
                        </div>

                        <div style="
                            padding:8px 10px;
                            border-radius:12px;
                            background:linear-gradient(180deg, #fffdf2 0%, #fff7d1 100%);
                            border:1px solid #fde68a;
                            text-align:right;
                            min-width:140px;
                        ">
                            <div style="font-size:11px; font-weight:700; color:#92400e; text-transform:uppercase; letter-spacing:0.08em;">
                                {{ $translations['monthly'] }}
                            </div>
                            <div style="margin-top:4px; font-size:18px; line-height:1.05; font-weight:800; color:#111827;">
                                EUR {{ number_format((float) $vehicle->dashboard_monthly_cost, 2, ',', '.') }}
                            </div>
                        </div>
                    </div>

                    <div style="
                        display:grid;
                        grid-template-columns:repeat(2, minmax(0, 1fr));
                        gap:10px;
                        margin-bottom:16px;
                    ">
                        <div style="
                            padding:12px 14px;
                            border-radius:14px;
                            background:#f8fafc;
                            border:1px solid #e2e8f0;
                        ">
                            <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.08em;">
                                {{ $translations['total_costs'] }}
                            </div>
                            <div style="margin-top:6px; font-size:18px; line-height:1.05; font-weight:800; color:#0f172a;">
                                EUR {{ number_format((float) $vehicle->dashboard_total_cost, 2, ',', '.') }}
                            </div>
                        </div>

                        <div style="
                            padding:12px 14px;
                            border-radius:14px;
                            background:#f8fafc;
                            border:1px solid #e2e8f0;
                        ">
                            <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.08em;">
                                {{ $translations['composition'] }}
                            </div>
                            <div style="margin-top:6px; font-size:13px; line-height:1.5; color:#334155;">
                                {{ $translations['composition_value'] }}
                            </div>
                        </div>
                    </div>

                    <div style="display:flex; gap:10px;">
                        <a href="/admin/vehicles/{{ $vehicle->id }}/edit"
                           style="
                            padding:10px 14px;
                            border-radius:10px;
                            background:#f3f4f6;
                            text-decoration:none;
                            font-size:13px;
                            color:#111827;
                           ">
                            {{ $translations['view'] }}
                        </a>

                        <a href="/admin/maintenance-logs/create?vehicle_id={{ $vehicle->id }}"
                           style="
                            padding:10px 14px;
                            border-radius:10px;
                            background:#ffd200;
                            color:#000;
                            text-decoration:none;
                            font-size:13px;
                            font-weight:600;
                           ">
                            {{ $translations['add_maintenance'] }}
                        </a>
                    </div>
                </div>

                <div
                    x-show="lightboxOpen"
                    x-cloak
                    @click.self="closeLightbox()"
                    style="
                        position:fixed;
                        inset:0;
                        background:rgba(0,0,0,0.9);
                        z-index:9999;
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        padding:24px;
                    "
                >
                    <div
                        style="
                            position:absolute;
                            inset:0;
                            display:flex;
                            align-items:center;
                            justify-content:center;
                            padding:24px;
                        "
                    >
                        <img
                            x-bind:src="photos[currentIndex]"
                            alt="{{ $vehicle->brand }} {{ $vehicle->model }}"
                            style="
                                display:block;
                                margin:auto;
                                max-width:90vw;
                                max-height:90vh;
                                border-radius:12px;
                                object-fit:contain;
                            "
                        >
                    </div>

                    <button
                        type="button"
                        x-show="{{ $hasMultiplePhotos ? 'true' : 'false' }}"
                        x-cloak
                        @click="prev()"
                        aria-label="{{ $translations['aria_previous_photo_zoom'] }}"
                        style="
                            position:absolute;
                            left:24px;
                            top:50%;
                            transform:translateY(-50%);
                            border:none;
                            background:transparent;
                            color:#fff;
                            font-size:48px;
                            cursor:pointer;
                            z-index:1;
                        "
                    >‹</button>

                    <button
                        type="button"
                        x-show="{{ $hasMultiplePhotos ? 'true' : 'false' }}"
                        x-cloak
                        @click="next()"
                        aria-label="{{ $translations['aria_next_photo_zoom'] }}"
                        style="
                            position:absolute;
                            right:24px;
                            top:50%;
                            transform:translateY(-50%);
                            border:none;
                            background:transparent;
                            color:#fff;
                            font-size:48px;
                            cursor:pointer;
                            z-index:1;
                        "
                    >›</button>

                    <button
                        type="button"
                        @click="closeLightbox()"
                        aria-label="{{ $translations['aria_close_gallery'] }}"
                        style="
                            position:absolute;
                            top:24px;
                            right:24px;
                            border:none;
                            background:transparent;
                            color:#fff;
                            font-size:36px;
                            cursor:pointer;
                            z-index:1;
                        "
                    >✕</button>
                </div>
            </div>

        @empty
            <div style="color:#9ca3af; display:flex; flex-direction:column; align-items:flex-start; gap:14px;">
                <div>{{ $translations['empty'] }}</div>

                <x-filament::button
                    :href="$createVehicleUrl"
                    tag="a"
                    color="warning"
                >
                    {{ $translations['empty_cta'] }}
                </x-filament::button>
            </div>
        @endforelse

    </x-filament::card>
</x-filament::widget>
