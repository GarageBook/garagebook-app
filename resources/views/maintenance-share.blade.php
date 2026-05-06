@php
    use App\Support\ImageThumbnail;
    use App\Support\MediaPath;
    use App\Support\PdfThumbnail;
@endphp
<!DOCTYPE html>
<html>
<head>
    <title>GarageBook Onderhoud</title>
    @if(! request()->is('maintenance/pdf'))
        <style>
            @font-face {
                font-family: "ZalandoSans";
                src: url("/fonts/zalandosans/ZalandoSans-Light.woff2") format("woff2");
                font-weight: 300;
                font-style: normal;
            }

            @font-face {
                font-family: "ZalandoSans";
                src: url("/fonts/zalandosans/ZalandoSans-Bold.woff2") format("woff2");
                font-weight: 700;
                font-style: normal;
            }
        </style>
    @endif
</head>
<body style="font-family:'ZalandoSans', Arial, sans-serif; margin:0; background:#fff;">

    <div style="background:black; color:white; padding:20px 40px;">
        <table style="width:100%;">
            <tr>
                <td style="vertical-align:middle;">
                    <img
                        src="{{ request()->is('maintenance/pdf') ? public_path('images/garagebook-logo-white.png') : asset('images/garagebook-logo-white.png') }}"
                        alt="GarageBook"
                        style="height:40px;"
                    >
                </td>

                <td style="text-align:right; vertical-align:middle;">
                    <a
                        href="https://garagebook.nl"
                        style="
                            background:#ffd200;
                            color:black;
                            padding:10px 18px;
                            text-decoration:none;
                            border-radius:12px;
                            font-weight:700;
                            font-family:'ZalandoSans', Arial, sans-serif;
                            display:inline-block;
                        "
                    >
                        Maak gratis jouw garage aan op GarageBook.nl
                    </a>
                </td>
            </tr>
        </table>
    </div>

    <div style="padding:40px; max-width:900px; margin:0 auto;">
        <h1 style="margin-bottom:10px;">Onderhoudstijdlijn</h1>

        @if(isset($vehicle))
            <div style="font-size:18px; color:#666; margin-bottom:30px;">
                {{ $vehicle->brand }} {{ $vehicle->model }}
            </div>
        @endif

        @foreach($logs as $log)
            @php
                $attachments = $log->attachments;

                $imageAttachments = array_values(array_filter(
                    $attachments,
                    fn (string $attachment) => MediaPath::isImage($attachment)
                ));

                $fileAttachments = array_values(array_filter(
                    $attachments,
                    fn (string $attachment) => ! MediaPath::isImage($attachment)
                ));

                $firstAttachment = $imageAttachments[0] ?? null;

                $imageSrc = null;
                $galleryImages = [];

                if ($firstAttachment && request()->is('maintenance/pdf')) {
                    $imageSrc = PdfThumbnail::fromPath(
                        storage_path('app/public/' . ltrim($firstAttachment, '/'))
                    );
                }

                if (! request()->is('maintenance/pdf')) {
                    $galleryImages = array_map(function (string $attachment): array {
                        $thumbnailPath = ImageThumbnail::path($attachment, 480) ?: $attachment;

                        return [
                            'thumbnail' => asset('storage/' . ltrim($thumbnailPath, '/')),
                            'full' => asset('storage/' . ltrim($attachment, '/')),
                        ];
                    }, $imageAttachments);
                }
            @endphp

            <div style="border-bottom:1px solid #ddd; padding:25px 0;">
                <table style="width:100%;">
                    <tr>
                        <td style="width:260px; vertical-align:top;">
                            @if(request()->is('maintenance/pdf'))
                                @if($imageSrc)
                                    <div style="
                                        width:240px;
                                        height:160px;
                                        border-radius:12px;
                                        overflow:hidden;
                                        background:#f3f4f6;
                                    ">
                                        <img
                                            src="{{ $imageSrc }}"
                                            loading="lazy"
                                            decoding="async"
                                            width="240"
                                            height="160"
                                            style="
                                                width:100%;
                                                height:100%;
                                                display:block;
                                                object-fit:cover;
                                            "
                                        >
                                    </div>
                                @else
                                    <div style="width:240px; height:160px; background:#f3f4f6; border-radius:12px;"></div>
                                @endif
                            @else
                                @if(count($galleryImages))
                                    <div
                                        class="gb-share-gallery"
                                        data-gallery='@json($galleryImages)'
                                        style="position:relative; width:240px;"
                                    >
                                        <button
                                            type="button"
                                            class="gb-share-gallery-prev"
                                            aria-label="Vorige foto"
                                            style="
                                                position:absolute;
                                                left:12px;
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
                                                display:none;
                                            "
                                        >‹</button>

                                        <button
                                            type="button"
                                            class="gb-share-gallery-open"
                                            aria-label="Open fotogalerij"
                                            style="
                                                width:100%;
                                                padding:0;
                                                border:none;
                                                background:transparent;
                                                cursor:pointer;
                                                display:block;
                                            "
                                        >
                                            <div style="
                                                width:240px;
                                                height:160px;
                                                border-radius:12px;
                                                overflow:hidden;
                                                background:#f3f4f6;
                                            ">
                                                <img
                                                    src="{{ $galleryImages[0]['thumbnail'] }}"
                                                    loading="lazy"
                                                    decoding="async"
                                                    width="240"
                                                    height="160"
                                                    class="gb-share-gallery-image"
                                                    style="
                                                        width:100%;
                                                        height:100%;
                                                        display:block;
                                                        object-fit:cover;
                                                    "
                                                >
                                            </div>
                                        </button>

                                        <button
                                            type="button"
                                            class="gb-share-gallery-next"
                                            aria-label="Volgende foto"
                                            style="
                                                position:absolute;
                                                right:12px;
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
                                                display:none;
                                            "
                                        >›</button>

                                        @if(count($galleryImages) > 1)
                                            <div
                                                class="gb-share-gallery-counter"
                                                style="
                                                    position:absolute;
                                                    left:12px;
                                                    bottom:12px;
                                                    padding:6px 10px;
                                                    border-radius:999px;
                                                    background:rgba(17,24,39,0.7);
                                                    color:#fff;
                                                    font-size:12px;
                                                    font-weight:600;
                                                    z-index:2;
                                                "
                                            >
                                                1 / {{ count($galleryImages) }}
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <div style="width:240px; height:160px; background:#f3f4f6; border-radius:12px;"></div>
                                @endif
                            @endif
                        </td>

                        <td style="vertical-align:top; padding-left:20px;">
                            <div style="font-weight:700; margin-bottom:10px;">
                                Beschrijving: {{ $log->description }}
                            </div>

                            <div style="margin-bottom:6px;">
                                Datum: {{ \Carbon\Carbon::parse($log->maintenance_date)->format('d-m-Y') }}
                            </div>

                            <div>
                                Kilometerstand: {{ $log->km_reading }} km
                            </div>

                            @if(count($fileAttachments) && ! request()->is('maintenance/pdf'))
                                <div style="margin-top:10px;">
                                    Bestanden:
                                    @foreach($fileAttachments as $attachment)
                                        @php
                                            $label = MediaPath::label($attachment);
                                            $url = asset('storage/' . ltrim($attachment, '/'));
                                        @endphp

                                        <div>
                                            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer">{{ $label }}</a>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                    </tr>
                </table>
            </div>
        @endforeach
    </div>

    @if(! request()->is('maintenance/pdf'))
        <div
            id="shareGalleryLightbox"
            style="
                display:none;
                position:fixed;
                inset:0;
                background:rgba(0,0,0,0.9);
                z-index:9999;
            "
        >
            <div
                id="shareGalleryLightboxCenter"
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
                    id="shareGalleryLightboxImage"
                    alt=""
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
                id="shareGalleryLightboxPrev"
                aria-label="Vorige foto in vergroting"
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
                    display:none;
                "
            >‹</button>

            <button
                type="button"
                id="shareGalleryLightboxNext"
                aria-label="Volgende foto in vergroting"
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
                    display:none;
                "
            >›</button>

            <button
                type="button"
                id="shareGalleryLightboxClose"
                aria-label="Sluit fotogalerij"
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

        <script>
            (function () {
                const galleries = document.querySelectorAll('.gb-share-gallery');
                const lightbox = document.getElementById('shareGalleryLightbox');
                const lightboxImage = document.getElementById('shareGalleryLightboxImage');
                const lightboxPrev = document.getElementById('shareGalleryLightboxPrev');
                const lightboxNext = document.getElementById('shareGalleryLightboxNext');
                const lightboxClose = document.getElementById('shareGalleryLightboxClose');
                const canHover = window.matchMedia('(hover: hover)').matches;

                let activeGallery = null;

                function renderGallery(gallery) {
                    const images = gallery.images;
                    const hasMultiple = images.length > 1;

                    gallery.image.src = images[gallery.index].thumbnail;

                    if (gallery.counter) {
                        gallery.counter.textContent = `${gallery.index + 1} / ${images.length}`;
                    }

                    gallery.prev.style.display = hasMultiple && (!canHover || gallery.hovering) ? 'block' : 'none';
                    gallery.next.style.display = hasMultiple && (!canHover || gallery.hovering) ? 'block' : 'none';
                }

                function renderLightbox() {
                    if (!activeGallery) {
                        return;
                    }

                    const images = activeGallery.images;
                    const hasMultiple = images.length > 1;

                    lightboxImage.src = images[activeGallery.index].full;
                    lightboxPrev.style.display = hasMultiple ? 'block' : 'none';
                    lightboxNext.style.display = hasMultiple ? 'block' : 'none';
                }

                function openLightbox(gallery) {
                    activeGallery = gallery;
                    renderLightbox();
                    lightbox.style.display = 'block';
                    document.body.style.overflow = 'hidden';
                }

                function closeLightbox() {
                    activeGallery = null;
                    lightbox.style.display = 'none';
                    document.body.style.overflow = '';
                }

                function nextImage(gallery) {
                    gallery.index = (gallery.index + 1) % gallery.images.length;
                    renderGallery(gallery);

                    if (activeGallery === gallery) {
                        renderLightbox();
                    }
                }

                function prevImage(gallery) {
                    gallery.index = (gallery.index - 1 + gallery.images.length) % gallery.images.length;
                    renderGallery(gallery);

                    if (activeGallery === gallery) {
                        renderLightbox();
                    }
                }

                galleries.forEach((node) => {
                    const gallery = {
                        node,
                        images: JSON.parse(node.dataset.gallery),
                        index: 0,
                        hovering: false,
                        image: node.querySelector('.gb-share-gallery-image'),
                        prev: node.querySelector('.gb-share-gallery-prev'),
                        next: node.querySelector('.gb-share-gallery-next'),
                        open: node.querySelector('.gb-share-gallery-open'),
                        counter: node.querySelector('.gb-share-gallery-counter'),
                    };

                    node.addEventListener('mouseenter', function () {
                        gallery.hovering = true;
                        renderGallery(gallery);
                    });

                    node.addEventListener('mouseleave', function () {
                        gallery.hovering = false;
                        renderGallery(gallery);
                    });

                    gallery.prev.addEventListener('click', function () {
                        prevImage(gallery);
                    });

                    gallery.next.addEventListener('click', function () {
                        nextImage(gallery);
                    });

                    gallery.open.addEventListener('click', function () {
                        openLightbox(gallery);
                    });

                    renderGallery(gallery);
                });

                lightboxPrev.addEventListener('click', function () {
                    if (activeGallery) {
                        prevImage(activeGallery);
                    }
                });

                lightboxNext.addEventListener('click', function () {
                    if (activeGallery) {
                        nextImage(activeGallery);
                    }
                });

                lightboxClose.addEventListener('click', closeLightbox);

                lightbox.addEventListener('click', function (event) {
                    if (event.target === lightbox || event.target.id === 'shareGalleryLightboxCenter') {
                        closeLightbox();
                    }
                });

                document.addEventListener('keydown', function (event) {
                    if (lightbox.style.display !== 'block' || !activeGallery) {
                        return;
                    }

                    if (event.key === 'ArrowRight') {
                        nextImage(activeGallery);
                    }

                    if (event.key === 'ArrowLeft') {
                        prevImage(activeGallery);
                    }

                    if (event.key === 'Escape') {
                        closeLightbox();
                    }
                });
            })();
        </script>
    @endif

</body>
</html>
