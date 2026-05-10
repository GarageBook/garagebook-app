@php
    $attachments = \App\Models\MaintenanceLog::normalizeAttachmentPaths($record?->attachments ?? []);
    $statePath = $mediaStatePath;
@endphp

<div
    x-data="{
        initialState: @js($attachments ?? []),
        state: @js($attachments ?? []),
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
        dynamicFiles() {
            if (! Array.isArray(this.state)) {
                return []
            }

            return this.state.filter((path) => ! this.initialState.includes(path))
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
                return @js(__('maintenance.media_gallery.file_fallback'))
            }

            return path.split('/').pop()
        },
        fileTypeLabel(path) {
            const extension = this.extension(path)

            if (! extension) {
                return @js(__('maintenance.media_gallery.file_fallback'))
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
        removeByPath(path) {
            if (! Array.isArray(this.state)) {
                this.state = [...this.initialState]
            }

            this.state = this.state.filter((item) => item !== path)
            this.$wire.set('{{ $statePath }}', [...this.state])
        },
    }"
    x-init="syncFromWire()"
    class="gb-maintenance-media-gallery"
>
    @if ($attachments !== [])
        <div class="gb-maintenance-media-gallery__grid">
            @foreach ($attachments as $attachment)
                @php
                    $extension = strtolower(pathinfo($attachment, PATHINFO_EXTENSION));
                    $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg'], true);
                    $isVideo = in_array($extension, ['mp4', 'mov', 'webm', 'm4v', 'avi'], true);
                    $url = str_starts_with($attachment, 'http://') || str_starts_with($attachment, 'https://') || str_starts_with($attachment, '/')
                        ? $attachment
                        : $storageBaseUrl . '/' . ltrim($attachment, '/');
                @endphp
                <div
                    class="gb-maintenance-media-gallery__card"
                    x-show="state.includes(@js($attachment))"
                >
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
                                {{ __('maintenance.media_gallery.open') }}
                            </a>

                            <button
                                type="button"
                                class="gb-maintenance-media-gallery__remove"
                                x-on:click="removeByPath(@js($attachment))"
                            >
                                {{ __('maintenance.media_gallery.remove') }}
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div x-cloak>
        <template x-if="dynamicFiles().length">
            <div class="gb-maintenance-media-gallery__grid">
                <template x-for="(file, index) in dynamicFiles()" :key="`${file}-${index}`">
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
                                    {{ __('maintenance.media_gallery.open') }}
                                </a>

                                <button
                                    type="button"
                                    class="gb-maintenance-media-gallery__remove"
                                    x-on:click="remove(index)"
                                >
                                    {{ __('maintenance.media_gallery.remove') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>
