@php
    $attachments = $attachments ?? [];
    $statePath = $mediaStatePath;
@endphp

<div
    x-data="{
        state: @js($attachments ?? []),
        ready: false,
        storageBaseUrl: @js($storageBaseUrl),
        syncFromWire() {
            const value = this.$wire.get('{{ $statePath }}')

            if (Array.isArray(value) && value.length) {
                this.state = value
                return
            }

            if (Array.isArray(this.state) && this.state.length) {
                this.$wire.set('{{ $statePath }}', [...this.state])
            }
        },
        isImage(path) {
            return ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg'].includes(this.extension(path))
        },
        isVideo(path) {
            return ['mp4', 'mov', 'webm', 'm4v', 'avi'].includes(this.extension(path))
        },
        isPreviewable(path) {
            return this.isImage(path) || this.isVideo(path)
        },
        extension(path) {
            if (! path || typeof path !== 'string') {
                return ''
            }

            return path.split('.').pop().toLowerCase()
        },
        fileUrl(path) {
            if (! path || typeof path !== 'string') {
                return ''
            }

            if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/')) {
                return path
            }

            return `${this.storageBaseUrl}/${path.replace(/^\\/+/, '')}`
        },
        fileLabel(path) {
            if (! path || typeof path !== 'string') {
                return 'Bestand'
            }

            return path.split('/').pop()
        },
        fileTypeLabel(path) {
            const extension = this.extension(path)

            if (! extension) {
                return 'Bestand'
            }

            return extension.toUpperCase()
        },
        remove(index) {
            if (! Array.isArray(this.state)) {
                return
            }

            this.state.splice(index, 1)
            this.$wire.set('{{ $statePath }}', [...this.state])
        },
    }"
    x-init="ready = true; syncFromWire()"
    class="gb-maintenance-media-gallery"
>
    @if ($attachments !== [])
        <div x-show="! ready" class="gb-maintenance-media-gallery__grid">
            @foreach ($attachments as $attachment)
                @php
                    $extension = strtolower(pathinfo($attachment, PATHINFO_EXTENSION));
                    $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg'], true);
                    $isVideo = in_array($extension, ['mp4', 'mov', 'webm', 'm4v', 'avi'], true);
                    $url = str_starts_with($attachment, 'http://') || str_starts_with($attachment, 'https://') || str_starts_with($attachment, '/')
                        ? $attachment
                        : $storageBaseUrl . '/' . ltrim($attachment, '/');
                @endphp
                <div class="gb-maintenance-media-gallery__card">
                    @if ($isImage)
                        <img
                            src="{{ $url }}"
                            alt="{{ basename($attachment) }}"
                            class="gb-maintenance-media-gallery__image"
                            loading="lazy"
                            decoding="async"
                        >
                    @elseif ($isVideo)
                        <video
                            src="{{ $url }}"
                            class="gb-maintenance-media-gallery__video"
                            controls
                            preload="metadata"
                            playsinline
                        ></video>
                    @else
                        <div class="gb-maintenance-media-gallery__file">
                            <div class="gb-maintenance-media-gallery__file-type">{{ strtoupper($extension ?: 'bestand') }}</div>
                            <div class="gb-maintenance-media-gallery__file-name">{{ basename($attachment) }}</div>
                        </div>
                    @endif

                    <div class="gb-maintenance-media-gallery__meta">
                        <div class="gb-maintenance-media-gallery__label">{{ basename($attachment) }}</div>

                        <div class="gb-maintenance-media-gallery__actions">
                            <a
                                href="{{ $url }}"
                                class="gb-maintenance-media-gallery__link"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Open
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div x-show="ready" x-cloak>
        <template x-if="Array.isArray(state) && state.length">
            <div class="gb-maintenance-media-gallery__grid">
                <template x-for="(file, index) in state" :key="`${file}-${index}`">
                    <div class="gb-maintenance-media-gallery__card">
                        <template x-if="isImage(file)">
                            <img
                                :src="fileUrl(file)"
                                :alt="fileLabel(file)"
                                class="gb-maintenance-media-gallery__image"
                                loading="lazy"
                                decoding="async"
                            >
                        </template>

                        <template x-if="isVideo(file)">
                            <video
                                :src="fileUrl(file)"
                                class="gb-maintenance-media-gallery__video"
                                controls
                                preload="metadata"
                                playsinline
                            ></video>
                        </template>

                        <template x-if="! isPreviewable(file)">
                            <div class="gb-maintenance-media-gallery__file">
                                <div class="gb-maintenance-media-gallery__file-type" x-text="fileTypeLabel(file)"></div>
                                <div class="gb-maintenance-media-gallery__file-name" x-text="fileLabel(file)"></div>
                            </div>
                        </template>

                        <div class="gb-maintenance-media-gallery__meta">
                            <div class="gb-maintenance-media-gallery__label" x-text="fileLabel(file)"></div>

                            <div class="gb-maintenance-media-gallery__actions">
                                <a
                                    :href="fileUrl(file)"
                                    class="gb-maintenance-media-gallery__link"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Open
                                </a>

                                <button
                                    type="button"
                                    class="gb-maintenance-media-gallery__remove"
                                    x-on:click="remove(index)"
                                >
                                    Verwijder
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>
