@php
    $statePath = $mediaStatePath;
@endphp

<div
    x-data="{
        state: $wire.entangle('{{ $statePath }}'),
        storageBaseUrl: @js($storageBaseUrl),
        isImage(path) {
            return ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg'].includes(this.extension(path))
        },
        isVideo(path) {
            return ['mp4', 'mov', 'webm', 'm4v', 'avi'].includes(this.extension(path))
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
        remove(index) {
            if (! Array.isArray(this.state)) {
                return
            }

            this.state.splice(index, 1)
        },
    }"
    x-cloak
    class="gb-maintenance-media-gallery"
>
    <template x-if="Array.isArray(state) && state.length">
        <div class="gb-maintenance-media-gallery__grid">
            <template x-for="(file, index) in state" :key="`${file}-${index}`">
                <div class="gb-maintenance-media-gallery__card">
                    <template x-if="isImage(file)">
                        <img
                            :src="fileUrl(file)"
                            :alt="fileLabel(file)"
                            class="gb-maintenance-media-gallery__image"
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

                    <div class="gb-maintenance-media-gallery__meta">
                        <div class="gb-maintenance-media-gallery__label" x-text="fileLabel(file)"></div>

                        <button
                            type="button"
                            class="gb-maintenance-media-gallery__remove"
                            x-on:click="remove(index)"
                        >
                            Verwijder
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </template>
</div>
