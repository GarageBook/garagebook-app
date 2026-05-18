@php
    /** @var \App\Models\TripLog|null $record */
    $photos = collect($record?->photos ?? [])
        ->filter(fn ($path) => is_string($path) && filled($path))
        ->values();
@endphp

@if($photos->isNotEmpty())
    <div style="display:grid;gap:0.85rem;grid-template-columns:repeat(auto-fit,minmax(8rem,1fr));">
        @foreach($photos as $index => $photo)
            <a
                href="{{ route('trip-photos.show', ['trip' => $record, 'photoIndex' => $index]) }}"
                target="_blank"
                rel="noopener noreferrer"
                style="display:block;overflow:hidden;border-radius:1rem;border:1px solid rgba(226,232,240,1);background:#f8fafc;aspect-ratio:1/1;"
            >
                <img
                    src="{{ route('trip-photos.show', ['trip' => $record, 'photoIndex' => $index]) }}"
                    alt="{{ $record->title ?: __('trips.model_label') }}"
                    loading="lazy"
                    decoding="async"
                    style="width:100%;height:100%;object-fit:cover;display:block;"
                >
            </a>
        @endforeach
    </div>
@endif
